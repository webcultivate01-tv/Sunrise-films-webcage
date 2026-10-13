<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * The receipt issued for one payment, frozen at the moment it was generated.
 *
 * Every figure and name here is a copy, not a join - the bill is the record,
 * so later edits to the project, the photographer or the company profile
 * never change a bill that has been issued. There is deliberately no update
 * or delete method: a correction is a new payment with a new bill.
 *
 * The company* properties are named like Setting's so the shared invoice
 * letterhead partial can render either one.
 */
final class PaymentBill
{
    public function __construct(
        public readonly int $id,
        public readonly int $paymentId,
        public readonly string $reference,
        public readonly string $billDate,
        public readonly ?string $photographerName,
        public readonly ?string $photographerPhone,
        public readonly ?string $photographerEmail,
        public readonly ?string $customerName,
        public readonly string $paymentType,
        public readonly string $paymentMethod,
        public readonly float $amount,
        public readonly float $projectTotal,
        public readonly float $collectedToDate,
        public readonly float $outstanding,
        public readonly ?string $notes,
        public readonly string $companyName,
        public readonly string $companyAddress,
        public readonly string $companyPhone,
        public readonly string $companyEmail,
        public readonly string $companyWebsite,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $nullable = static fn (string $key): ?string => isset($row[$key]) ? (string) $row[$key] : null;

        return new self(
            id:                (int) $row['id'],
            paymentId:         (int) $row['payment_id'],
            reference:         (string) $row['reference'],
            billDate:          (string) $row['bill_date'],
            photographerName:  $nullable('photographer_name'),
            photographerPhone: $nullable('photographer_phone'),
            photographerEmail: $nullable('photographer_email'),
            customerName:      $nullable('customer_name'),
            paymentType:       (string) $row['payment_type'],
            paymentMethod:     (string) $row['payment_method'],
            amount:            (float) $row['amount'],
            projectTotal:      (float) $row['project_total'],
            collectedToDate:   (float) $row['collected_to_date'],
            outstanding:       (float) $row['outstanding'],
            notes:             $nullable('notes'),
            companyName:       (string) $row['company_name'],
            companyAddress:    (string) $row['company_address'],
            companyPhone:      (string) $row['company_phone'],
            companyEmail:      (string) $row['company_email'],
            companyWebsite:    (string) $row['company_website'],
        );
    }

    public static function findByPayment(int $paymentId): ?self
    {
        $row = Database::selectOne('SELECT * FROM payment_bills WHERE payment_id = ? LIMIT 1', [$paymentId]);

        return $row === null ? null : self::fromRow($row);
    }

    /**
     * Issue the bill. INSERT IGNORE on the unique payment_id keeps this
     * idempotent: a bill that already exists is never overwritten.
     */
    public static function issue(
        int $paymentId,
        string $reference,
        string $billDate,
        ?string $photographerName,
        ?string $photographerPhone,
        ?string $photographerEmail,
        ?string $customerName,
        string $paymentType,
        string $paymentMethod,
        float $amount,
        float $projectTotal,
        float $collectedToDate,
        ?string $notes,
        Setting $company,
    ): void {
        Database::statement(
            'INSERT IGNORE INTO payment_bills (
                payment_id, reference, bill_date,
                photographer_name, photographer_phone, photographer_email, customer_name,
                payment_type, payment_method, amount, project_total, collected_to_date, outstanding, notes,
                company_name, company_address, company_phone, company_email, company_website
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $paymentId, $reference, $billDate,
                $photographerName, $photographerPhone, $photographerEmail, $customerName,
                $paymentType, $paymentMethod, $amount, $projectTotal, $collectedToDate,
                max(0.0, $projectTotal - $collectedToDate), $notes,
                $company->companyName, $company->companyAddress, $company->companyPhone,
                $company->companyEmail, $company->companyWebsite,
            ],
        );
    }
}
