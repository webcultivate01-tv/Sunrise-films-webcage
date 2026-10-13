<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Core\Validator;
use App\Models\Payment;
use App\Models\PaymentBill;
use App\Models\Photographer;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Support\PngLogo;
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
     * All projects with a pending (non-paid) payment status, unfiltered -
     * used for the Call Pending tab on the Payment Dashboard.
     *
     * @return list<array{project: Project, collected: float, outstanding: float, status: string, paymentsCount: int, lastPaymentDate: ?string}>
     */
    public static function pendingProjects(User $actor): array
    {
        self::assertAccess($actor);

        return array_values(array_filter(
            self::rows(),
            static fn (array $row): bool => $row['status'] !== self::STATUS_PAID,
        ));
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

        $payment = Payment::findById($id) ?? throw new \RuntimeException('The payment could not be recorded.');

        // The bill is issued the instant the payment exists, from the figures
        // as they stand right now, and is never recomputed afterwards.
        self::issueBill($payment, Payment::totalForProject($payment->projectId));

        return $payment;
    }

    /**
     * The bill issued for $payment. Every payment gets one when it is
     * recorded (and the 24_payment_bills migration backfilled the older
     * ones); the fallback here covers a payment that somehow has none by
     * issuing it once from what is on file, so the figures are then frozen
     * like any other bill.
     */
    public static function billFor(Payment $payment): PaymentBill
    {
        $bill = PaymentBill::findByPayment($payment->id);

        if ($bill !== null) {
            return $bill;
        }

        $collected = 0.0;

        foreach (Payment::timelineForProject($payment->projectId) as $entry) {
            $collected += $entry->amount;

            if ($entry->id === $payment->id) {
                break;
            }
        }

        self::issueBill($payment, $collected);

        return PaymentBill::findByPayment($payment->id)
            ?? throw new \RuntimeException('The bill could not be generated.');
    }

    private static function issueBill(Payment $payment, float $collectedToDate): void
    {
        $project      = Project::findById($payment->projectId);
        $photographer = $payment->photographerId !== null ? Photographer::findById($payment->photographerId) : null;

        PaymentBill::issue(
            paymentId:         $payment->id,
            reference:         self::billReference($payment),
            billDate:          $payment->paymentDate,
            photographerName:  $photographer?->name ?? $payment->photographerName,
            photographerPhone: $photographer?->phone,
            photographerEmail: $photographer?->email,
            customerName:      $payment->customerName,
            paymentType:       $payment->paymentType,
            paymentMethod:     $payment->paymentMethod,
            amount:            $payment->amount,
            projectTotal:      $project?->totalPayment ?? $payment->projectTotalPayment ?? 0.0,
            collectedToDate:   $collectedToDate,
            notes:             $payment->notes,
            company:           Setting::current(),
        );
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
     * never sent. Built from the frozen bill, so it always matches it.
     */
    public static function billWhatsAppMessage(PaymentBill $bill): string
    {
        return implode("\n", [
            'Hello ' . ($bill->photographerName ?? 'there') . ',',
            '',
            'Here is your payment receipt ' . $bill->reference . ' from ' . $bill->companyName . '.',
            '',
            'Customer: ' . ($bill->customerName ?? '-'),
            'Payment type: ' . payment_type_label($bill->paymentType),
            'Payment method: ' . payment_method_label($bill->paymentMethod),
            'Amount received: ' . money($bill->amount),
            'Payment date: ' . date('j M Y', strtotime($bill->billDate)),
            'Project total: ' . money($bill->projectTotal),
            'Outstanding balance: ' . money($bill->outstanding),
            '',
            'The receipt PDF is attached. Thank you!',
        ]);
    }

    /**
     * Build the Bill/Receipt PDF from the frozen bill - laid out like the
     * on-screen receipt (logo and company block, receipt no/date strip,
     * photographer and customer, payment rows, outstanding balance and the
     * signatory line), and identical whether it is downloaded straight after
     * a work is added or later from Payment Management.
     */
    public static function generateBillPdf(PaymentBill $bill): string
    {
        $pdf   = new SimplePdf();
        $ink   = [0.067, 0.090, 0.208];
        $muted = [0.39, 0.45, 0.55];
        $faint = [0.58, 0.64, 0.72];
        $rule  = [0.89, 0.91, 0.94];
        $tint  = [0.94, 0.95, 1.0];

        $cardL = 40.0;
        $cardR = $pdf->pageWidth() - 40.0;
        $cardT = $pdf->pageHeight() - 40.0;
        $left  = $cardL + 28.0;
        $right = $cardR - 28.0;
        $mid   = $cardL + ($cardR - $cardL) / 2 + 10.0;

        // ============ Header: logo left, company block right ============
        $logo = PngLogo::grayscale(BASE_PATH . '/public/assets/img/sunrise-mark.png');

        if ($logo !== null) {
            $pdf->grayImage($logo['data'], $logo['width'], $logo['height'], $left, $cardT - 28.0 - 42.0, 42.0, 42.0);
        }

        $y = $cardT - 40.0;
        $pdf->textRight($right, $y, $bill->companyName, 16, true, $ink);

        foreach (array_filter([$bill->companyPhone, $bill->companyEmail, $bill->companyWebsite, $bill->companyAddress]) as $line) {
            $y -= 12;
            $pdf->textRight($right, $y, $line, 8.5, false, $muted);
        }

        $y = min($y, $cardT - 28.0 - 42.0) - 18.0;
        $pdf->line($cardL, $y, $cardR, $y, 0.75, $rule);

        // ============ Reference strip ============
        $stripH = 36.0;
        $pdf->rect($cardL, $y - $stripH, $cardR - $cardL, $stripH, $tint);
        $textY = $y - 22.0;
        $pdf->text($left, $textY, 'Receipt No:', 10, true, $ink);
        $pdf->text($left + $pdf->textWidth('Receipt No: ', 10, true), $textY, $bill->reference, 10, false, $muted);

        $date = date('j M Y', strtotime($bill->billDate));
        $pdf->textRight($right, $textY, $date, 10, false, $muted);
        $pdf->textRight($right - $pdf->textWidth($date . ' ', 10), $textY, 'Receipt Date:', 10, true, $ink);

        $y -= $stripH + 28.0;

        // ============ Photographer / Customer ============
        $pdf->text($left, $y, 'PHOTOGRAPHER', 8, true, $faint);
        $pdf->text($mid, $y, 'CUSTOMER', 8, true, $faint);

        $colTop = $y - 16.0;
        $pdf->text($left, $colTop, $bill->photographerName ?? 'Unknown photographer', 11, true, $ink);
        $pdf->text($mid, $colTop, $bill->customerName ?? 'Unknown customer', 11, true, $ink);

        $detailY = $colTop;

        foreach (array_filter([$bill->photographerPhone, $bill->photographerEmail]) as $line) {
            $detailY -= 14.0;
            $pdf->text($left, $detailY, $line, 9.5, false, $muted);
        }

        $y = $detailY - 22.0;
        $pdf->line($cardL, $y, $cardR, $y, 0.75, $rule);
        $y -= 14.0;

        // ============ Payment details ============
        $rows = [
            ['Payment type', payment_type_label($bill->paymentType), null, false],
            ['Payment method', payment_method_label($bill->paymentMethod), null, false],
            ['Amount received', null, $bill->amount, true],
            ['Project total value', null, $bill->projectTotal, false],
            ['Total collected to date', null, $bill->collectedToDate, false],
        ];

        foreach ($rows as [$label, $text, $money, $strong]) {
            $y -= 22.0;
            $pdf->text($left, $y, $label, 10, false, $muted);

            if ($money !== null) {
                $pdf->rupeeRight($right, $y, number_format($money, 2), $strong ? 12 : 10, true, $ink);
            } else {
                $pdf->textRight($right, $y, (string) $text, 10, true, $ink);
            }

            $pdf->line($left, $y - 8.0, $right, $y - 8.0, 0.5, $rule);
        }

        // ============ Outstanding balance ============
        $y -= 22.0;
        $boxH = 34.0;
        $pdf->roundedRect($left, $y - $boxH + 6.0, $right - $left, $boxH, 6, $tint);

        $balanceColor = $bill->outstanding > 0.0 ? [0.86, 0.15, 0.15] : [0.02, 0.59, 0.41];
        $pdf->text($left + 14.0, $y - 14.0, 'Outstanding Balance', 11, true, $ink);
        $pdf->rupeeRight($right - 14.0, $y - 14.0, number_format($bill->outstanding, 2), 12, true, $balanceColor);

        $y -= $boxH + 10.0;

        if ($bill->notes !== null && $bill->notes !== '') {
            $y -= 6.0;
            $pdf->text($left, $y, 'NOTES', 8, true, $faint);

            foreach ($pdf->wrap($bill->notes, $right - $left, 9.5) as $line) {
                $y -= 13.0;
                $pdf->text($left, $y, $line, 9.5, false, $ink);
            }

            $y -= 8.0;
        }

        // ============ Footer: signatory ============
        $y -= 12.0;
        $pdf->line($cardL, $y, $cardR, $y, 0.75, $rule);

        $sigL = $right - 140.0;
        $pdf->textCentered($sigL + 70.0, $y - 30.0, 'For ' . $bill->companyName, 10, false, $muted);
        $pdf->line($sigL, $y - 78.0, $right, $y - 78.0, 0.75, [0.72, 0.77, 0.84]);
        $pdf->textCentered($sigL + 70.0, $y - 90.0, 'Authorised Signatory', 8.5, false, $muted);

        // The card outline goes on last so it is sized to what was written.
        $cardB = $y - 110.0;
        $pdf->roundedRect($cardL, $cardB, $cardR - $cardL, $cardT - $cardB, 14, null, [0.82, 0.84, 0.88], 1.5);

        return $pdf->output();
    }
}
