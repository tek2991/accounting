<?php

namespace Tek2991\Accounting\Services;

use Illuminate\Support\Collection;
use Tek2991\Accounting\Models\Tax;
use Tek2991\Accounting\Models\TaxComponent;
use Tek2991\Accounting\Enums\TaxType;

class TaxService
{
    /**
     * Calculate the tax components for a given taxable amount.
     * Amount is expected in minor units (e.g. cents).
     *
     * @param int|float $taxableAmount
     * @param Tax $tax
     * @return Collection
     */
    public function calculateTax(int|float $taxableAmount, Tax $tax): Collection
    {
        $calculatedComponents = collect();
        $totalTaxAmount = 0;

        foreach ($tax->components as $component) {
            /** @var TaxComponent $component */
            $rate = (float) $component->rate;
            
            // For exclusive tax, we calculate: amount * (rate / 100)
            // For inclusive tax, we calculate: amount * (rate / (100 + total_tax_rate))
            // But usually, items have base price, and inclusive tax means the price INCLUDES tax.
            // If the item price is 118, and tax is 18%, the base price is 100, tax is 18.
            
            if ($tax->type === TaxType::Inclusive) {
                $totalRate = $tax->total_rate;
                // e.g. 118 * (9 / 118) = 9
                $taxAmount = $taxableAmount * ($rate / (100 + $totalRate));
            } else {
                // e.g. 100 * (9 / 100) = 9
                $taxAmount = $taxableAmount * ($rate / 100);
            }
            
            $taxAmount = (int) round($taxAmount);
            $totalTaxAmount += $taxAmount;

            $calculatedComponents->push([
                'component_id' => $component->id,
                'name'         => $component->name,
                'rate'         => $rate,
                'account_id'   => $component->account_id,
                'amount'       => $taxAmount, // in minor units
            ]);
        }

        return $calculatedComponents;
    }
}
