<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Core\Validator;
use App\Models\SalarySettlement;
use App\Models\Setting;
use App\Models\TaskSalaryCredit;
use App\Models\User;
use App\Support\SimplePdf;
use PDOException;

/**
 * Monthly Salary (salary spec).
 *
 * Admin and Manager share the settlement side of this service, and both now
 * have the same system-wide reach (module spec update): a Manager sees and
 * may settle salary for every Employee, not just the ones they created. The
 * Employee side ("My Salary") only ever reads or acts on the signed-in
 * Employee's own id, proven by the caller before any of the read-only
 * aggregation methods below are reached.
 *
 * Earnings are never stored anywhere - they are always recomputed from the
 * permanent `task_salary_credits` rows created by Task Management, so a
 * completed task's amount and a settlement's amount can never drift apart.
 */
final class SalaryService
{
    // === Admin / Manager: access and scope =================================

    public static function canAccessManagement(User $actor): bool
    {
        return in_array($actor->role, [User::ROLE_ADMIN, User::ROLE_MANAGER], true);
    }

    public static function assertAccessManagement(User $actor): void
    {
        if (!self::canAccessManagement($actor)) {
            throw HttpException::forbidden('You are not authorized to access Monthly Salary.');
        }
    }

    /**
     * The Employees $actor may view salary for: a Manager's scope was widened
     * to match Admin, so both see every Employee in the system.
     *
     * @return list<User>
     */
    public static function assignableEmployees(User $actor): array
    {
        self::assertAccessManagement($actor);

        return User::allOfRole(User::ROLE_EMPLOYEE);
    }

    /**
     * @return list<int>|null null = no scope. Always null now that a
     * Manager's reach matches Admin's (module spec update).
     */
    private static function scopeEmployeeIds(User $actor): ?array
    {
        return null;
    }

    /**
     * Load one employee and prove their salary is inside $actor's scope -
     * a 403, never a 404, for an employee a Manager does not manage.
     */
    public static function findEmployeeOrFail(User $actor, int $employeeId): User
    {
        self::assertAccessManagement($actor);

        $employee = User::findById($employeeId);

        if ($employee === null || $employee->role !== User::ROLE_EMPLOYEE) {
            throw HttpException::notFound('That employee could not be found.');
        }

        $scope = self::scopeEmployeeIds($actor);

        if ($scope !== null && !in_array($employee->id, $scope, true)) {
            throw HttpException::forbidden("You are not authorized to view that employee's salary.");
        }

        return $employee;
    }

    /**
     * Load one settlement and prove it is inside $actor's scope.
     */
    public static function findSettlementOrFail(User $actor, int $id): SalarySettlement
    {
        self::assertAccessManagement($actor);

        $settlement = SalarySettlement::findById($id);

        if ($settlement === null) {
            throw HttpException::notFound('That settlement could not be found.');
        }

        $scope = self::scopeEmployeeIds($actor);

        if ($scope !== null && !in_array($settlement->employeeId, $scope, true)) {
            throw HttpException::forbidden('You are not authorized to view that settlement.');
        }

        return $settlement;
    }

    public static function findSettlementByIdempotencyKey(string $key): ?SalarySettlement
    {
        return SalarySettlement::findByIdempotencyKey($key);
    }

    // === Employee: own settlements ==========================================

    public static function findOwnSettlementOrFail(User $employee, int $id): SalarySettlement
    {
        $settlement = SalarySettlement::findById($id);

        if ($settlement === null || $settlement->employeeId !== $employee->id) {
            throw HttpException::notFound('That settlement could not be found.');
        }

        return $settlement;
    }

    // === Read-only aggregation ===============================================
    // Every method below trusts $employeeId has already been authorized by the
    // caller (findEmployeeOrFail for Admin/Manager, or trivially the signed-in
    // Employee's own id) - the same level of trust TaskService::myWork() places
    // in the employee id it is handed.

    /**
     * @return array{earned: float, paid: float, outstanding: float, status: string}
     */
    public static function totals(int $employeeId): array
    {
        $earned = TaskSalaryCredit::totalForEmployee($employeeId);
        $paid   = SalarySettlement::totalSettledForEmployee($employeeId);

        return [
            'earned'      => $earned,
            'paid'        => $paid,
            'outstanding' => $earned - $paid,
            'status'      => self::statusFor($earned, $paid),
        ];
    }

    /**
     * Salary status (spec s17): Pending while nothing has been settled yet,
     * Fully Settled once payments catch up with earnings, otherwise Partially
     * Settled. A half-cent tolerance absorbs rounding on stored decimals.
     */
    public static function statusFor(float $earned, float $paid): string
    {
        if ($paid <= 0.0) {
            return 'pending';
        }

        if ($paid + 0.005 >= $earned) {
            return 'fully_settled';
        }

        return 'partially_settled';
    }

    /**
     * Whether $month is a real 'YYYY-MM' calendar month, as typed into the
     * Monthly Salary month filter or the Settlement form.
     */
    public static function isValidMonth(string $month): bool
    {
        return $month !== '' && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1;
    }

    /**
     * The Employee Salary List (spec s3): every employee in scope, narrowed
     * by the search box, with the summary figures the list displays.
     *
     * $month optionally narrows the "this month" figures (earned/paid/
     * outstanding for that one month) to any calendar month on record rather
     * than always the current one, so an Admin/Manager can look back at any
     * employee's history for a given month. It never affects the all-time
     * earned/paid/outstanding/status figures.
     *
     * @return list<array{employee: User, earned: float, paid: float, outstanding: float, status: string, currentMonthEarned: float, periodPaid: float, periodOutstanding: float, periodStatus: string}>
     */
    public static function listEmployees(User $actor, string $search = '', string $month = ''): array
    {
        self::assertAccessManagement($actor);

        $employees = self::assignableEmployees($actor);

        if ($search !== '') {
            $needle    = mb_strtolower($search);
            $employees = array_values(array_filter($employees, static function (User $employee) use ($needle): bool {
                return str_contains(mb_strtolower($employee->name), $needle)
                    || str_contains(mb_strtolower($employee->email), $needle)
                    || (string) $employee->id === $needle;
            }));
        }

        $period = self::isValidMonth($month) ? $month : date('Y-m');

        return array_map(static function (User $employee) use ($period): array {
            $totals       = self::totals($employee->id);
            $periodEarned = TaskSalaryCredit::totalForEmployeeMonth($employee->id, $period);
            $periodPaid   = SalarySettlement::totalSettledForEmployeeMonth($employee->id, $period);

            return [
                'employee'           => $employee,
                'earned'             => $totals['earned'],
                'paid'               => $totals['paid'],
                'outstanding'        => $totals['outstanding'],
                'status'             => $totals['status'],
                'currentMonthEarned' => $periodEarned,
                'periodPaid'         => $periodPaid,
                'periodOutstanding'  => $periodEarned - $periodPaid,
                'periodStatus'       => self::statusFor($periodEarned, $periodPaid),
            ];
        }, $employees);
    }

    /**
     * Live suggestions for the Monthly Salary search box.
     *
     * @return list<array{employee: User, earned: float, paid: float, outstanding: float, status: string, currentMonthEarned: float}>
     */
    public static function suggestEmployees(User $actor, string $search): array
    {
        if (trim($search) === '') {
            return [];
        }

        return array_slice(self::listEmployees($actor, $search), 0, 8);
    }

    /**
     * The month summary shown atop the Employee Salary List: what $actor's
     * whole scope earned in $month, what has already been paid against it,
     * and what is still left to pay - the same earned/paid figures each row
     * shows, just totalled across every employee in scope instead of one at
     * a time. Defaults to the current calendar month when $month is blank or
     * not a real 'YYYY-MM' month.
     *
     * @return array{earned: float, paid: float, toPay: float, month: string}
     */
    public static function currentMonthSummary(User $actor, string $month = ''): array
    {
        self::assertAccessManagement($actor);

        $scope  = self::scopeEmployeeIds($actor);
        $period = self::isValidMonth($month) ? $month : date('Y-m');
        $earned = TaskSalaryCredit::totalForScopeMonth($scope, $period);
        $paid   = SalarySettlement::totalSettledForScopeMonth($scope, $period);

        return [
            'earned' => $earned,
            'paid'   => $paid,
            'toPay'  => $earned - $paid,
            'month'  => $period,
        ];
    }

    /**
     * The Monthly Salary Breakdown (spec s5): one row per calendar month that
     * has ever had earnings or a settlement, newest first. Previous months
     * are never recomputed away - each is read straight from the permanent
     * credit/settlement rows for that month.
     *
     * @return list<array{month: string, earned: float, paid: float, outstanding: float, status: string}>
     */
    public static function monthlyBreakdown(int $employeeId): array
    {
        $earned = TaskSalaryCredit::monthlyTotalsForEmployee($employeeId);
        $paid   = SalarySettlement::monthlyTotalsForEmployee($employeeId);

        $months = array_unique(array_merge(array_keys($earned), array_keys($paid)));
        rsort($months);

        $rows = [];

        foreach ($months as $month) {
            $monthEarned = $earned[$month] ?? 0.0;
            $monthPaid   = $paid[$month] ?? 0.0;

            $rows[] = [
                'month'       => $month,
                'earned'      => $monthEarned,
                'paid'        => $monthPaid,
                'outstanding' => $monthEarned - $monthPaid,
                'status'      => self::statusFor($monthEarned, $monthPaid),
            ];
        }

        return $rows;
    }

    /**
     * The Dashboard's "Monthly Salary" chart: always $months calendar months,
     * oldest first, zero-filled for any month with no earnings - unlike
     * monthlyBreakdown() above, which only lists months that actually had
     * activity and would otherwise draw a chart with a single bar.
     *
     * @return list<array{month: string, earned: float}>
     */
    public static function monthlyTrend(int $employeeId, int $months = 6): array
    {
        $earned = TaskSalaryCredit::monthlyTotalsForEmployee($employeeId);

        $rows = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month  = date('Y-m', strtotime("-{$i} months"));
            $rows[] = [
                'month'  => $month,
                'earned' => $earned[$month] ?? 0.0,
            ];
        }

        return $rows;
    }

    private static function monthOutstanding(int $employeeId, string $month): float
    {
        return TaskSalaryCredit::totalForEmployeeMonth($employeeId, $month)
            - SalarySettlement::totalSettledForEmployeeMonth($employeeId, $month);
    }

    /**
     * The detail entries behind one employee's one-month figures (spec s5):
     * the individual completed-task credits and the settlements made against
     * that month.
     *
     * @return array{credits: list<array<string, mixed>>, settlements: list<SalarySettlement>, earned: float, paid: float, outstanding: float, status: string}
     */
    public static function monthEntries(int $employeeId, string $month): array
    {
        $earned = TaskSalaryCredit::totalForEmployeeMonth($employeeId, $month);
        $paid   = SalarySettlement::totalSettledForEmployeeMonth($employeeId, $month);

        return [
            'credits'     => TaskSalaryCredit::forEmployeeMonth($employeeId, $month),
            'settlements' => SalarySettlement::forEmployee($employeeId, ['month' => $month]),
            'earned'      => $earned,
            'paid'        => $paid,
            'outstanding' => $earned - $paid,
            'status'      => self::statusFor($earned, $paid),
        ];
    }

    /**
     * The merged, newest-first Salary Earned + Settlement history (spec s12,
     * s13), scoped to $employeeIds (null = every employee).
     *
     * @param  list<int>|null        $employeeIds
     * @param  array<string, string> $filters
     * @return list<array{type: string, date: string, employeeId: int, employeeName: string, amount: float, reference: ?string, settlementId: ?int, detail: string}>
     */
    private static function mergedHistory(?array $employeeIds, array $filters): array
    {
        $type    = $filters['type'] ?? '';
        $entries = [];

        if ($type !== 'settlement') {
            foreach (TaskSalaryCredit::creditsForScope($employeeIds, $filters) as $row) {
                $entries[] = [
                    'type'         => 'salary_earned',
                    'date'         => (string) $row['credited_at'],
                    'employeeId'   => (int) $row['employee_id'],
                    'employeeName' => (string) $row['employee_name'],
                    'amount'       => (float) $row['amount'],
                    'reference'    => null,
                    'settlementId' => null,
                    'detail'       => (string) $row['task_title'] . ' - ' . (string) ($row['customer_name'] ?? 'Unknown customer'),
                ];
            }
        }

        if ($type !== 'salary_earned') {
            foreach (SalarySettlement::allForScope($employeeIds, $filters) as $settlement) {
                $entries[] = [
                    'type'         => 'settlement',
                    'date'         => $settlement->settledAt,
                    'employeeId'   => $settlement->employeeId,
                    'employeeName' => $settlement->employeeNameSnapshot,
                    'amount'       => $settlement->amount,
                    'reference'    => $settlement->referenceNo,
                    'settlementId' => $settlement->id,
                    'detail'       => 'Settlement for ' . pretty_month($settlement->salaryMonth),
                ];
            }
        }

        usort($entries, static fn (array $a, array $b): int => strcmp((string) $b['date'], (string) $a['date']));

        return $entries;
    }

    /**
     * Global History (spec s12): every salary transaction $actor's scope
     * covers, filterable by employee, type, month, reference and date range.
     *
     * @param array<string, string> $filters
     * @return list<array<string, mixed>>
     */
    public static function globalHistory(User $actor, array $filters = []): array
    {
        self::assertAccessManagement($actor);

        return self::mergedHistory(self::scopeEmployeeIds($actor), $filters);
    }

    /**
     * Live suggestions for the Salary History search box.
     *
     * @return list<array<string, mixed>>
     */
    public static function suggestHistory(User $actor, string $search): array
    {
        if (trim($search) === '') {
            return [];
        }

        return array_slice(self::globalHistory($actor, ['q' => $search]), 0, 8);
    }

    /**
     * Employee-wise History (spec s13): one employee's own salary
     * transactions. $employeeId must already be authorized by the caller.
     *
     * @param array<string, string> $filters
     * @return list<array<string, mixed>>
     */
    public static function employeeHistory(int $employeeId, array $filters = []): array
    {
        return self::mergedHistory([$employeeId], $filters);
    }

    // === Settlement ==========================================================

    /**
     * Validate the Settlement form (spec s7): a real calendar month and an
     * amount that does not exceed what is still outstanding for that month.
     *
     * @param array<string, string> $input
     */
    public static function validateSettlement(User $actor, User $employee, array $input): Validator
    {
        self::assertAccessManagement($actor);

        $validator = new Validator();

        $month  = trim($input['salary_month'] ?? '');
        $amount = trim($input['amount'] ?? '');

        $validator->require('salary_month', $month, 'Please select a salary month.');

        if ($month !== '' && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            $validator->add('salary_month', 'That salary month is not valid.');
        }

        $validator->require('amount', $amount, 'Please enter a settlement amount.');
        $validator->decimal('amount', $amount, 'Please enter a valid amount.');

        if ($amount !== '' && !array_key_exists('amount', $validator->errors())) {
            if ((float) $amount <= 0) {
                $validator->add('amount', 'The settlement amount must be greater than zero.');
            } elseif ($month !== '' && !array_key_exists('salary_month', $validator->errors())) {
                $outstanding = self::monthOutstanding($employee->id, $month);

                if ((float) $amount > $outstanding + 0.005) {
                    $validator->add('amount', sprintf(
                        'The settlement amount cannot exceed the outstanding balance of %s for %s.',
                        money($outstanding),
                        pretty_month($month),
                    ));
                }
            }
        }

        return $validator;
    }

    /**
     * Confirm a settlement (spec s7-s10): snapshots the employee's details and
     * that month's figures onto a permanent row, then assigns it a bill
     * reference. A repeated submission carrying the same idempotency key -
     * whether caught here or racing past validation into a duplicate-key
     * insert - always returns the one settlement that was actually created,
     * never a second one (spec s18).
     *
     * @param array<string, string> $input
     */
    public static function settle(User $actor, User $employee, array $input): SalarySettlement
    {
        self::assertAccessManagement($actor);
        self::findEmployeeOrFail($actor, $employee->id);

        $key = trim($input['idempotency_key'] ?? '');

        if ($key !== '') {
            $existing = SalarySettlement::findByIdempotencyKey($key);

            if ($existing !== null) {
                return $existing;
            }
        } else {
            $key = bin2hex(random_bytes(16));
        }

        $month  = trim($input['salary_month']);
        $amount = (float) $input['amount'];

        $monthEarned      = TaskSalaryCredit::totalForEmployeeMonth($employee->id, $month);
        $previousSettled  = SalarySettlement::totalSettledForEmployeeMonth($employee->id, $month);
        $outstandingAfter = $monthEarned - $previousSettled - $amount;

        try {
            $id = SalarySettlement::create(
                employeeId:       $employee->id,
                salaryMonth:      $month,
                amount:           $amount,
                monthEarned:      $monthEarned,
                previousSettled:  $previousSettled,
                outstandingAfter: $outstandingAfter,
                idempotencyKey:   $key,
                employeeName:     $employee->name,
                employeeEmail:    $employee->email,
                employeePhone:    $employee->phone,
                settledBy:        $actor->id,
                notes:            trim($input['notes'] ?? ''),
            );
        } catch (PDOException $e) {
            // A second request racing the same idempotency key past the check
            // above hits the unique index instead - recover the row it lost to.
            if ((string) $e->getCode() === '23000') {
                $existing = SalarySettlement::findByIdempotencyKey($key);

                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $e;
        }

        return SalarySettlement::findById($id) ?? throw new \RuntimeException('The settlement could not be created.');
    }

    // === Bill (PDF) ==========================================================

    /**
     * Build the settlement bill as a PDF (spec s8-s9), entirely from the
     * frozen figures on $settlement - never from the employee's current
     * salary - so a bill never changes after it is generated (spec s18).
     */
    public static function generateBillPdf(SalarySettlement $settlement): string
    {
        $pdf   = new SimplePdf();
        $left  = 50.0;
        $right = $pdf->pageWidth() - 50.0;
        $y     = 792.0;

        $company = Setting::current();

        $pdf->text($left, $y, $company->companyName, 18, true);
        $y -= 20;

        $contact = trim($company->companyPhone . ($company->companyEmail !== '' ? '   |   ' . $company->companyEmail : ''));

        foreach (array_filter([$company->companyAddress, $contact]) as $line) {
            $pdf->text($left, $y, $line, 9);
            $y -= 13;
        }

        $y -= 8;
        $pdf->line($left, $y, $right, $y);
        $y -= 26;

        $pdf->text($left, $y, 'SALARY SETTLEMENT BILL', 14, true);
        $pdf->text($right - 160, $y, 'Reference: ' . $settlement->referenceNo, 10, true);
        $y -= 16;
        $pdf->text($right - 160, $y, 'Date: ' . date('j M Y', strtotime($settlement->settledAt)), 10);
        $y -= 30;

        $pdf->text($left, $y, 'Employee', 11, true);
        $y -= 17;

        $employeeFields = [
            'Name'        => $settlement->employeeNameSnapshot,
            'Employee ID' => 'EMP-' . str_pad((string) $settlement->employeeId, 4, '0', STR_PAD_LEFT),
            'Email'       => $settlement->employeeEmailSnapshot,
            'Mobile'      => $settlement->employeePhoneSnapshot ?? 'Not recorded',
        ];

        foreach ($employeeFields as $label => $value) {
            $pdf->text($left, $y, $label . ':', 10, true);
            $pdf->text($left + 120, $y, $value, 10);
            $y -= 16;
        }

        $y -= 8;
        $pdf->line($left, $y, $right, $y);
        $y -= 26;

        $pdf->text($left, $y, 'Salary Details', 11, true);
        $y -= 18;

        $rows = [
            ['Salary month', pretty_month($settlement->salaryMonth)],
            ['Total earnings for the month', self::pdfMoney($settlement->monthEarned)],
            ['Previously settled', self::pdfMoney($settlement->previousSettled)],
            ['This settlement', self::pdfMoney($settlement->amount)],
            ['Remaining outstanding', self::pdfMoney($settlement->outstandingAfter)],
            ['Settlement date', date('j M Y, g:i a', strtotime($settlement->settledAt))],
        ];

        foreach ($rows as [$label, $value]) {
            $pdf->text($left, $y, $label, 10);
            $pdf->text($left + 260, $y, $value, 10, true);
            $y -= 18;
        }

        if ($settlement->notes !== null && $settlement->notes !== '') {
            $y -= 8;
            $pdf->text($left, $y, 'Notes: ' . $settlement->notes, 9);
            $y -= 18;
        }

        $y -= 16;
        $pdf->line($left, $y, $right, $y);
        $y -= 20;
        $pdf->text($left, $y, 'This is a system-generated bill and does not require a signature.', 8);

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
