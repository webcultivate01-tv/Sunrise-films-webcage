<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Core\Validator;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Support\SimplePdf;

/**
 * Work Management. Admins and Managers share full reach over the project
 * list, the same shape as Customer Management: both may register, view, edit
 * and change the status of any project; only an Admin may delete one outright.
 *
 * Employees have no access to this module.
 */
final class ProjectService
{
    public static function canAccess(User $actor): bool
    {
        return in_array($actor->role, [User::ROLE_ADMIN, User::ROLE_MANAGER], true);
    }

    public static function canDelete(User $actor): bool
    {
        return $actor->role === User::ROLE_ADMIN;
    }

    public static function assertAccess(User $actor): void
    {
        if (!self::canAccess($actor)) {
            throw HttpException::forbidden('You are not authorized to access Work Management.');
        }
    }

    /**
     * The project list, narrowed by the search box, the status filter and a
     * specific customer.
     *
     * A completed project drops out of the default (no status filter) list
     * the moment every task on it is done - the same way a fully paid
     * project disappears from Payment Management's default list - and only
     * reappears once the Admin/Manager explicitly filters by "Completed".
     *
     * @return list<Project>
     */
    public static function list(User $actor, string $search = '', string $status = '', ?int $customerId = null, string $sort = ''): array
    {
        self::assertAccess($actor);

        if (!in_array($status, Project::statuses(), true)) {
            $status = '';
        }

        $projects = Project::all($search, $status, $customerId, $sort);

        if ($status !== '') {
            return $projects;
        }

        return array_values(array_filter(
            $projects,
            static fn (Project $project): bool => $project->status !== Project::STATUS_COMPLETED,
        ));
    }

    /**
     * Live suggestions for the search box.
     *
     * @return list<Project>
     */
    public static function suggest(User $actor, string $search): array
    {
        self::assertAccess($actor);

        if (trim($search) === '') {
            return [];
        }

        return array_slice(Project::all($search), 0, 8);
    }

    public static function findOrFail(User $actor, int $id): Project
    {
        self::assertAccess($actor);

        return Project::findById($id) ?? throw HttpException::notFound('That project could not be found.');
    }

    /**
     * Validate the project form. $existing is unused today (nothing about the
     * validation differs between add and edit) but kept for symmetry with the
     * other modules' validate() signatures.
     *
     * @param array<string, string> $input
     */
    public static function validate(array $input, ?Project $existing = null): Validator
    {
        $validator = new Validator();

        $customerId  = trim($input['customer_id'] ?? '');
        $name        = trim($input['name'] ?? '');
        $description = trim($input['description'] ?? '');
        $folderName  = trim($input['folder_name'] ?? '');
        $deadline    = trim($input['deadline'] ?? '');
        $total       = trim($input['total_payment'] ?? '');

        $validator->require('customer_id', $customerId, 'Please select a customer.');

        if ($customerId !== '' && Customer::findById((int) $customerId) === null) {
            $validator->add('customer_id', 'That customer could not be found.');
        }

        $validator->require('name', $name, 'Please enter the project name.');
        $validator->maxLength('name', $name, 150, 'Name must be 150 characters or fewer.');

        $validator->require('description', $description, 'Please enter a project description.');

        $validator->require('folder_name', $folderName, 'Please enter the receivable folder name.');
        $validator->maxLength('folder_name', $folderName, 190, 'Folder name must be 190 characters or fewer.');

        $validator->require('deadline', $deadline, 'Please choose a deadline.');
        $validator->date('deadline', $deadline);

        $validator->require('total_payment', $total, 'Please enter the total payment.');
        $validator->decimal('total_payment', $total, 'Please enter a valid total payment amount.');

        // The advance payment fields only apply when registering a new
        // project - editing an existing one never touches its payments.
        if ($existing === null) {
            $advanceAmount = trim($input['advance_amount'] ?? '');
            $advanceMethod = trim($input['advance_payment_method'] ?? '');

            if ($advanceAmount !== '') {
                $validator->decimal('advance_amount', $advanceAmount, 'Please enter a valid advance amount.');

                if (!array_key_exists('advance_amount', $validator->errors())) {
                    if ((float) $advanceAmount < 0) {
                        $validator->add('advance_amount', 'The advance amount cannot be negative.');
                    } elseif (
                        $total !== ''
                        && !array_key_exists('total_payment', $validator->errors())
                        && (float) $advanceAmount > (float) $total + 0.005
                    ) {
                        $validator->add('advance_amount', 'The advance amount cannot exceed the total payment.');
                    }
                }

                if ((float) $advanceAmount > 0) {
                    $validator->require('advance_payment_method', $advanceMethod, 'Please select how the advance was paid.');

                    if ($advanceMethod !== '') {
                        $validator->in(
                            'advance_payment_method',
                            $advanceMethod,
                            [Payment::METHOD_CASH, Payment::METHOD_UPI],
                            'That payment method is not recognised.',
                        );
                    }
                }
            }
        }

        return $validator;
    }

    /**
     * @param array<string, string> $input
     */
    public static function create(User $actor, array $input): Project
    {
        self::assertAccess($actor);

        $id = Project::create(
            customerId:     (int) $input['customer_id'],
            name:           trim($input['name']),
            description:    trim($input['description']),
            folderName:     trim($input['folder_name']),
            deadline:       trim($input['deadline']),
            totalPayment:   (float) $input['total_payment'],
            createdBy:      $actor->id,
        );

        return Project::findById($id)
            ?? throw new \RuntimeException('The project could not be created.');
    }

    /**
     * @param array<string, string> $input
     */
    public static function update(User $actor, Project $project, array $input): Project
    {
        self::assertAccess($actor);

        Project::update(
            $project->id,
            (int) $input['customer_id'],
            trim($input['name']),
            trim($input['description']),
            trim($input['folder_name']),
            trim($input['deadline']),
            (float) $input['total_payment'],
        );

        return Project::findById($project->id)
            ?? throw new \RuntimeException('The project could not be found after updating.');
    }

    public static function setStatus(User $actor, Project $project, string $status): void
    {
        self::assertAccess($actor);

        if (!in_array($status, Project::statuses(), true)) {
            throw HttpException::forbidden('That project status is not recognised.');
        }

        Project::updateStatus($project->id, $status);
    }

    /**
     * Keep a project's status in step, in real time, with the actual progress
     * of the tasks Task Management has assigned against it: pending until
     * work starts, in progress while any task is still open, and completed
     * only once every current task on it has been completed. Called after
     * every task lifecycle event that could change the picture (assigned,
     * completed, exited/cancelled, reassigned) so the Payment Management and
     * Work Management views never show a stale status.
     *
     * A project the Admin/Manager has explicitly marked Cancelled is left
     * alone - that is a deliberate, permanent state that task activity never
     * overwrites.
     */
    public static function recomputeStatusFromTasks(int $projectId): void
    {
        $project = Project::findById($projectId);

        if ($project === null || $project->status === Project::STATUS_CANCELLED) {
            return;
        }

        $counts = Task::statusCountsForProject($projectId);
        $total  = array_sum($counts);

        if ($total === 0) {
            return;
        }

        $completed = $counts[Task::STATUS_COMPLETED] ?? 0;
        $newStatus = $completed === $total ? Project::STATUS_COMPLETED : Project::STATUS_IN_PROGRESS;

        if ($newStatus !== $project->status) {
            Project::updateStatus($projectId, $newStatus);
        }
    }

    /**
     * Remove the record for good. Admin only - see canDelete().
     */
    public static function delete(User $actor, Project $project): void
    {
        self::assertAccess($actor);

        if (!self::canDelete($actor)) {
            throw HttpException::forbidden(
                'You are not authorized to delete projects. Mark it cancelled instead.',
            );
        }

        if (Payment::existsForProject($project->id)) {
            throw HttpException::forbidden(
                'This project has payment history and cannot be deleted. Mark it cancelled instead.',
            );
        }

        Project::delete($project->id);
    }

    /**
     * The permanent invoice reference for a project - derived from its
     * immutable id, the same way billReference() works for a single payment.
     */
    public static function billReference(Project $project): string
    {
        return 'INV-' . str_pad((string) $project->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Build the project Invoice/Bill PDF: the project's full picture - value,
     * every payment collected against it, and what remains outstanding - not
     * just a single transaction the way a payment receipt is.
     */
    public static function generateBillPdf(Project $project): string
    {
        $customer  = Customer::findById($project->customerId);
        $company   = Setting::current();
        $timeline  = Payment::timelineForProject($project->id);
        $collected = Payment::totalForProject($project->id);
        $reference = self::billReference($project);

        $pdf     = new SimplePdf();
        $left    = 50.0;
        $right   = $pdf->pageWidth() - 50.0;
        $white   = [1.0, 1.0, 1.0];
        $tint    = [0.82, 0.83, 0.90];
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

        $pdf->text($left, $y, 'PROJECT INVOICE', 14, true);
        $pdf->text($right - 200, $y, 'Invoice No: ' . $reference, 10, true);
        $y -= 16;
        $pdf->text($right - 200, $y, 'Invoice Date: ' . date('j M Y'), 10);
        $y -= 30;

        $pdf->text($left, $y, 'Bill To', 11, true);
        $y -= 17;

        $customerFields = [
            'Customer' => $customer?->name ?? 'Unknown customer',
            'Phone'    => $customer?->phone ?? '-',
            'Email'    => $customer?->email ?? '-',
            'Address'  => $customer?->address ?? '-',
        ];

        foreach ($customerFields as $label => $value) {
            $pdf->text($left, $y, $label . ':', 10, true);
            $pdf->text($left + 100, $y, $value, 10);
            $y -= 16;
        }

        $y -= 8;
        $pdf->line($left, $y, $right, $y);
        $y -= 26;

        $pdf->text($left, $y, 'Project', 11, true);
        $y -= 17;

        $projectFields = [
            'Project name' => $project->name,
            'Folder'       => $project->folderName,
            'Deadline'     => date('j M Y', strtotime($project->deadline)),
            'Status'       => project_status_label($project->status),
        ];

        foreach ($projectFields as $label => $value) {
            $pdf->text($left, $y, $label . ':', 10, true);
            $pdf->text($left + 100, $y, $value, 10);
            $y -= 16;
        }

        $y -= 8;
        $pdf->line($left, $y, $right, $y);
        $y -= 24;

        $pdf->text($left, $y, 'Payments Received', 11, true);
        $y -= 18;

        if ($timeline === []) {
            $pdf->text($left, $y, 'No payments recorded yet.', 10);
            $y -= 16;
        } else {
            $pdf->text($left, $y, 'Date', 9, true);
            $pdf->text($left + 90, $y, 'Type', 9, true);
            $pdf->text($left + 220, $y, 'Method', 9, true);
            $pdf->text($left + 340, $y, 'Reference', 9, true);
            $pdf->text($right - 90, $y, 'Amount', 9, true);
            $y -= 6;
            $pdf->line($left, $y, $right, $y, 0.5);
            $y -= 14;

            $maxRows = 25;

            foreach (array_slice($timeline, 0, $maxRows) as $entry) {
                $pdf->text($left, $y, date('j M Y', strtotime($entry->paymentDate)), 9);
                $pdf->text($left + 90, $y, payment_type_label($entry->paymentType), 9);
                $pdf->text($left + 220, $y, payment_method_label($entry->paymentMethod), 9);
                $pdf->text($left + 340, $y, $entry->referenceNo ?? '-', 9);
                $pdf->text($right - 90, $y, self::pdfMoney($entry->amount), 9);
                $y -= 15;
            }

            if (count($timeline) > $maxRows) {
                $pdf->text($left, $y, sprintf('+ %d more payment(s) - see full history in Payment Management.', count($timeline) - $maxRows), 8);
                $y -= 16;
            }
        }

        $y -= 8;
        $pdf->line($left, $y, $right, $y);
        $y -= 26;

        $outstanding = max(0.0, $project->totalPayment - $collected);

        $totals = [
            ['Total project value', self::pdfMoney($project->totalPayment), [0, 0, 0]],
            ['Total received', self::pdfMoney($collected), [0.02, 0.55, 0.35]],
            ['Balance due', self::pdfMoney($outstanding), $outstanding > 0.0 ? [0.75, 0.1, 0.1] : [0.02, 0.55, 0.35]],
        ];

        foreach ($totals as [$label, $value, $color]) {
            $pdf->text($left, $y, $label, 10);
            $pdf->text($right - 160, $y, $value, 11, true, $color);
            $y -= 18;
        }

        $y -= 6;
        $pdf->text($left, $y, 'Amount in words: ' . amount_in_words($project->totalPayment), 9);
        $y -= 16;
        $pdf->text($left, $y, 'Payment status: ' . payment_status_label(PaymentService::statusFor($project, $collected)), 9, true);

        $y -= 24;
        $pdf->line($left, $y, $right, $y);
        $y -= 20;
        $pdf->text($left, $y, 'This is a system-generated invoice and does not require a signature.', 8);

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
