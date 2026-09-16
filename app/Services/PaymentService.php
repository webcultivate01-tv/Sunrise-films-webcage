<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Core\Validator;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Support\SimplePdf;

/**
 * Payment Management. Admins and Managers share full reach over every
 * project's payments, the same shape as Work Management: both may view the
 * payment dashboard, record payments and drill into any project's history.
 *
 * A project's collected/outstanding figures and payment status are never
 * stored - they are always recomputed from the permanent Payment rows, so
 * they can never drift out of sync with the transaction history (module
 * spec: "previous payment records should never be overwritten").
 */
final class PaymentService
{
    public const STATUS_DUE              = 'due';
    public const STATUS_ADVANCE_RECEIVED = 'advance_received';
    public const STATUS_PARTIAL          = 'partial';
    public const STATUS_PAID             = 'paid';
    public const STATUS_OVERDUE          = 'overdue';

    /** @return list<string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_DUE, self::STATUS_ADVANCE_RECEIVED, self::STATUS_PARTIAL,
            self::STATUS_PAID, self::STATUS_OVERDUE,
        ];
    }

    public static function canAccess(User $actor): bool
    {
        return in_array($actor->role, [User::ROLE_ADMIN, User::ROLE_MANAGER], true);
    }

    public static function assertAccess(User $actor): void
    {
        if (!self::canAccess($actor)) {
            throw HttpException::forbidden('You are not authorized to access Payment Management.');
        }
    }

    public static function findOrFail(User $actor, int $id): Payment
    {
        self::assertAccess($actor);

        return Payment::findById($id) ?? throw HttpException::notFound('That payment could not be found.');
    }

    /**
     * A project's payment status (module spec s1): Paid once collected
     * catches up with the total, Overdue once the deadline has passed with
     * money still outstanding, Due while nothing has come in, Advance
     * Received while only the advance has, Partial otherwise.
     */
    public static function statusFor(Project $project, float $collected): string
    {
        if ($project->totalPayment > 0.0 && $collected + 0.005 >= $project->totalPayment) {
            return self::STATUS_PAID;
        }

        if ($project->isOverdue()) {
            return self::STATUS_OVERDUE;
        }

        if ($collected <= 0.0) {
            return self::STATUS_DUE;
        }

        if (Payment::isOnlyAdvance($project->id)) {
            return self::STATUS_ADVANCE_RECEIVED;
        }

        return self::STATUS_PARTIAL;
    }

    /**
     * Every project with its collected/outstanding figures and computed
     * status, narrowed by the search box and a specific photographer, but not by
     * payment status - the raw material for both projectOverview() and
     * dashboardSummary(), which need different slices of it.
     *
     * @return list<array{project: Project, collected: float, outstanding: float, status: string, paymentsCount: int, lastPaymentDate: ?string}>
     */
    private static function rows(string $search = '', ?int $photographerId = null): array
    {
        $rows = [];

        foreach (Project::all($search, '', $photographerId) as $project) {
            $collected = Payment::totalForProject($project->id);

            $rows[] = [
                'project'         => $project,
                'collected'       => $collected,
                'outstanding'     => max(0.0, $project->totalPayment - $collected),
                'status'          => self::statusFor($project, $collected),
                'paymentsCount'   => Payment::countForProject($project->id),
                'lastPaymentDate' => Payment::lastPaymentDateForProject($project->id),
            ];
        }

        return $rows;
    }

    /**
     * The Project Payment Overview (module spec s1, s9): every project with
     * its collected/outstanding figures and computed status, narrowed by the
     * search box, a status filter and a specific photographer.
     *
     * A fully paid project drops out of the default (no status filter) list
     * once the photographer settles it in full - it only reappears when the
     * admin/manager explicitly filters by the "Fully Paid" status.
     *
     * @return list<array{project: Project, collected: float, outstanding: float, status: string, paymentsCount: int, lastPaymentDate: ?string}>
     */
    public static function projectOverview(User $actor, string $search = '', string $status = '', ?int $photographerId = null): array
    {
        self::assertAccess($actor);

        $rows = self::rows($search, $photographerId);

        if (in_array($status, self::statuses(), true)) {
            return array_values(array_filter($rows, static fn (array $row): bool => $row['status'] === $status));
        }

        return array_values(array_filter($rows, static fn (array $row): bool => $row['status'] !== self::STATUS_PAID));
    }

    /**
     * Every project's collected/outstanding figures, keyed by project id -
     * lets the Record Payment form show a project's running total the moment
     * it is picked, without waiting on a payment to actually be posted.
     *
     * @return array<int, array{total: float, collected: float, outstanding: float}>
     */
    public static function projectPaymentIndex(User $actor): array
    {
        self::assertAccess($actor);

        $index = [];

        foreach (self::rows() as $row) {
            $index[$row['project']->id] = [
                'total'       => $row['project']->totalPayment,
                'collected'   => $row['collected'],
                'outstanding' => $row['outstanding'],
            ];
        }

        return $index;
    }

    /**
     * Live suggestions for the Payment Management search box.
     *
     * @return list<array{project: Project, collected: float, outstanding: float, status: string, paymentsCount: int, lastPaymentDate: ?string}>
     */
    public static function suggestProjects(User $actor, string $search): array
    {
        if (trim($search) === '') {
            return [];
        }

        return array_slice(self::projectOverview($actor, $search), 0, 8);
    }

    /**
     * The Payment Dashboard KPIs (module spec s4), across every project.
     *
     * @return array{totalProjectValue: float, totalCollected: float, totalOutstanding: float, advanceCollected: float, dueThisMonth: float, overduePayments: float, fullyPaid: int, partiallyPaid: int}
     */
    public static function dashboardSummary(User $actor): array
    {
        self::assertAccess($actor);

        $totalProjectValue = 0.0;
        $totalCollected    = 0.0;
        $dueThisMonth       = 0.0;
        $overduePayments    = 0.0;
        $fullyPaid          = 0;
        $partiallyPaid      = 0;
        $thisMonth          = date('Y-m');

        foreach (self::rows() as $row) {
            $project = $row['project'];

            $totalProjectValue += $project->totalPayment;
            $totalCollected    += $row['collected'];

            if ($row['status'] === self::STATUS_PAID) {
                $fullyPaid++;
            } elseif (in_array($row['status'], [self::STATUS_PARTIAL, self::STATUS_ADVANCE_RECEIVED], true)) {
                $partiallyPaid++;
            }

            if ($row['status'] === self::STATUS_OVERDUE) {
                $overduePayments += $row['outstanding'];
            } elseif ($row['outstanding'] > 0.0 && substr($project->deadline, 0, 7) === $thisMonth) {
                $dueThisMonth += $row['outstanding'];
            }
        }

        return [
            'totalProjectValue' => $totalProjectValue,
            'totalCollected'    => $totalCollected,
            'totalOutstanding'  => max(0.0, $totalProjectValue - $totalCollected),
            'advanceCollected'  => Payment::totalByType(Payment::TYPE_ADVANCE),
            'dueThisMonth'      => $dueThisMonth,
            'overduePayments'   => $overduePayments,
            'fullyPaid'         => $fullyPaid,
            'partiallyPaid'     => $partiallyPaid,
        ];
    }

    /**
     * How many projects currently sit in each payment status - the main
     * panel dashboard's payment-status breakdown chart.
     *
     * @return array<string, int>
     */
    public static function statusCounts(User $actor): array
    {
        self::assertAccess($actor);

        $counts = array_fill_keys(self::statuses(), 0);

        foreach (self::rows() as $row) {
            $counts[$row['status']]++;
        }

        return $counts;
    }

    /**
     * The Payment History (module spec s2, s5): every transaction, narrowed
     * by the search box and the available filters.
     *
     * @param array<string, string> $filters
     * @return list<Payment>
     */
    public static function history(User $actor, array $filters = []): array
    {
        self::assertAccess($actor);

        return Payment::all($filters);
    }

    /**
     * Live suggestions for the Payment History search box.
     *
     * @return list<Payment>
     */
    public static function suggestHistory(User $actor, string $search): array
    {
        if (trim($search) === '') {
            return [];
        }

        return array_slice(self::history($actor, ['q' => $search]), 0, 8);
    }

    /**
     * One project's payment summary and timeline (module spec s3, s8), shown
     * inside Work Management's project page.
     *
     * @return array{totalValue: float, collected: float, outstanding: float, status: string, collectionPercentage: float, paymentsCount: int, lastPaymentDate: ?string, timeline: list<Payment>}
     */
    public static function projectSummary(User $actor, Project $project): array
    {
        self::assertAccess($actor);

        $collected = Payment::totalForProject($project->id);

        return [
            'totalValue'           => $project->totalPayment,
            'collected'            => $collected,
            'outstanding'          => max(0.0, $project->totalPayment - $collected),
            'status'               => self::statusFor($project, $collected),
            'collectionPercentage' => $project->totalPayment > 0.0
                ? min(100.0, $collected / $project->totalPayment * 100)
                : 0.0,
            'paymentsCount'   => Payment::countForProject($project->id),
            'lastPaymentDate' => Payment::lastPaymentDateForProject($project->id),
            'timeline'        => Payment::timelineForProject($project->id),
        ];
    }

    /**
     * Validate the Record Payment form (module spec s2, s6): a real project,
     * a positive amount that does not push the project past its total value,
     * a recognised type/method and a real payment date.
     *
     * @param array<string, string> $input
     */
    public static function validate(array $input): Validator
    {
        $validator = new Validator();

        $projectId = trim($input['project_id'] ?? '');
        $amount    = trim($input['amount'] ?? '');
        $type      = trim($input['payment_type'] ?? '');
        $method    = trim($input['payment_method'] ?? '');
        $date      = trim($input['payment_date'] ?? '');
        $reference = trim($input['reference_no'] ?? '');

        $validator->require('project_id', $projectId, 'Please select a project.');

        $project = $projectId !== '' ? Project::findById((int) $projectId) : null;

        if ($projectId !== '' && $project === null) {
            $validator->add('project_id', 'That project could not be found.');
        }

        $validator->require('payment_type', $type, 'Please select a payment type.');

        if ($type !== '') {
            $validator->in('payment_type', $type, Payment::types(), 'That payment type is not recognised.');
        }

        $validator->require('payment_method', $method, 'Please select a payment method.');

        if ($method !== '') {
            $validator->in('payment_method', $method, Payment::methods(), 'That payment method is not recognised.');
        }

        $validator->require('payment_date', $date, 'Please choose the payment date.');
        $validator->date('payment_date', $date);

        $validator->require('amount', $amount, 'Please enter the payment amount.');
        $validator->decimal('amount', $amount, 'Please enter a valid amount.');

        if ($amount !== '' && !array_key_exists('amount', $validator->errors())) {
            if ((float) $amount <= 0) {
                $validator->add('amount', 'The payment amount must be greater than zero.');
            } elseif ($project !== null) {
                $outstanding = max(0.0, $project->totalPayment - Payment::totalForProject($project->id));

                if ((float) $amount > $outstanding + 0.005) {
                    $validator->add('amount', sprintf(
                        'The payment amount cannot exceed the outstanding balance of %s for this project.',
                        money($outstanding),
                    ));
                } elseif ($type === Payment::TYPE_FULL && abs((float) $amount - $outstanding) > 0.005) {
                    // "Full Payment" means the project is settled by this one
                    // transaction - anything less belongs under one of the
                    // part-payment types, or the bill would say the project is
                    // paid off when it is not.
                    $validator->add('amount', sprintf(
                        'A full payment has to clear the whole outstanding balance of %s. Pick another payment type to record a part of it.',
                        money($outstanding),
                    ));
                }
            }
        }

        $validator->maxLength('reference_no', $reference, 60, 'Reference number must be 60 characters or fewer.');

        return $validator;
    }

    /**
     * Record a new payment (module spec s10): always an insert, never an
     * update - the complete financial trail is preserved.
     *
     * @param array<string, string> $input
     */
    public static function record(User $actor, array $input): Payment
    {
        self::assertAccess($actor);

        $reference = trim($input['reference_no'] ?? '');
        $notes     = trim($input['notes'] ?? '');

        $id = Payment::create(
            projectId:     (int) $input['project_id'],
            amount:        (float) $input['amount'],
            paymentType:   trim($input['payment_type']),
            paymentMethod: trim($input['payment_method']),
            paymentDate:   trim($input['payment_date']),
            referenceNo:   $reference !== '' ? $reference : null,
            notes:         $notes !== '' ? $notes : null,
            receivedBy:    $actor->id,
        );

        return Payment::findById($id) ?? throw new \RuntimeException('The payment could not be recorded.');
    }

    /**
     * The permanent bill reference for a payment - derived from its
     * immutable id rather than a separate counter table, since (unlike
     * `reference_no`, which the person recording the payment types in
     * themselves) it always needs to exist and never needs to change.
     */
    public static function billReference(Payment $payment): string
    {
        return 'PAY-' . str_pad((string) $payment->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * The message pre-typed into WhatsApp when an Admin/Manager sends a
     * receipt on from the bill screen.
     *
     * WhatsApp's click-to-chat links cannot carry a file, so the bill itself
     * is downloaded alongside this and attached by hand - the message says so
     * rather than leaving the photographer waiting for an attachment that was
     * never sent.
     */
    public static function billWhatsAppMessage(Payment $payment, float $total, float $collected): string
    {
        $company     = Setting::current();
        $outstanding = max(0.0, $total - $collected);

        return implode("\n", [
            'Hello ' . ($payment->photographerName ?? 'there') . ',',
            '',
            'Here is your payment receipt ' . self::billReference($payment) . ' from ' . $company->companyName . '.',
            '',
            'Customer: ' . ($payment->customerName ?? '-'),
            'Payment type: ' . payment_type_label($payment->paymentType),
            'Payment method: ' . payment_method_label($payment->paymentMethod),
            'Amount received: ' . money($payment->amount),
            'Payment date: ' . date('j M Y', strtotime($payment->paymentDate)),
            'Project total: ' . money($total),
            'Outstanding balance: ' . money($outstanding),
            '',
            'The receipt PDF is attached. Thank you!',
        ]);
    }

    /**
     * Build the Bill/Receipt PDF for one payment - the same document whether
     * it is downloaded straight from Work Management (the advance collected
     * when a project is created) or later from Payment Management.
     */
    public static function generateBillPdf(Payment $payment): string
    {
        $project   = Project::findById($payment->projectId);
        $company   = Setting::current();
        $collected = Payment::totalForProject($payment->projectId);
        $total     = $project?->totalPayment ?? $payment->projectTotalPayment ?? 0.0;

        $outstanding = max(0.0, $total - $collected);

        $pdf    = new SimplePdf();
        $left   = 50.0;
        $right  = $pdf->pageWidth() - 50.0;
        $white  = [1.0, 1.0, 1.0];
        $tint   = [0.82, 0.83, 0.90];
        $bannerH = 96.0;

        // ============ Header banner: dark letterhead, company block right-aligned ============
        $pdf->rect(0, $pdf->pageHeight() - $bannerH, $pdf->pageWidth(), $bannerH, [0.067, 0.090, 0.208]);

        $y = $pdf->pageHeight() - 32.0;
        $pdf->textRight($right, $y, $company->companyName, 16, true, $white);
        $y -= 14;

        foreach (array_filter([$company->companyAddress, $company->companyPhone, $company->companyWebsite, $company->companyEmail]) as $line) {
            $pdf->textRight($right, $y, $line, 9, false, $tint);
            $y -= 11;
        }

        $y = $pdf->pageHeight() - $bannerH - 30.0;

        $reference = self::billReference($payment);

        $pdf->text($left, $y, 'PAYMENT RECEIPT', 14, true);
        $pdf->text($right - 160, $y, 'Reference: ' . $reference, 10, true);
        $y -= 16;
        $pdf->text($right - 160, $y, 'Date: ' . date('j M Y', strtotime($payment->paymentDate)), 10);
        $y -= 30;

        $pdf->text($left, $y, 'Photographer', 11, true);
        $y -= 17;

        $photographerFields = [
            'Name'     => $payment->photographerName ?? 'Unknown photographer',
            'Customer' => $payment->customerName ?? 'Unknown customer',
        ];

        foreach ($photographerFields as $label => $value) {
            $pdf->text($left, $y, $label . ':', 10, true);
            $pdf->text($left + 120, $y, $value, 10);
            $y -= 16;
        }

        $y -= 8;
        $pdf->line($left, $y, $right, $y);
        $y -= 26;

        $pdf->text($left, $y, 'Payment Details', 11, true);
        $y -= 18;

        $rows = [
            ['Payment method', payment_method_label($payment->paymentMethod)],
            ['Amount received', self::pdfMoney($payment->amount)],
            ['Project total value', self::pdfMoney($total)],
            ['Total collected to date', self::pdfMoney($collected)],
        ];

        foreach ($rows as [$label, $value]) {
            $pdf->text($left, $y, $label, 10);
            $pdf->text($left + 260, $y, $value, 10, true);
            $y -= 18;
        }

        $y -= 4;
        $pdf->text($left, $y, 'Outstanding balance', 11, true);
        $pdf->text($left + 260, $y, self::pdfMoney($outstanding), 11, true, $outstanding > 0.0 ? [0.75, 0.1, 0.1] : [0.02, 0.55, 0.35]);
        $y -= 22;

        if ($payment->notes !== null && $payment->notes !== '') {
            $pdf->text($left, $y, 'Notes: ' . $payment->notes, 9);
            $y -= 18;
        }

        $y -= 12;
        $pdf->line($left, $y, $right, $y);
        $y -= 20;
        $pdf->text($left, $y, 'This is a system-generated receipt and does not require a signature.', 8);

        return $pdf->output();
    }

    /**
     * Money formatted for the PDF: the base-14 fonts used there do not carry
     * a rupee-sign glyph, so an "INR" prefix is used instead of money().
     */
    private static function pdfMoney(float $amount): string
    {
        return 'INR ' . number_format($amount, 2);
    }
}
