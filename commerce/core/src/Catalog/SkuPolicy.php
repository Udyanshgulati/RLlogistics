<?php

declare(strict_types=1);

namespace Commerce\Core\Catalog;

/**
 * SKU format rules. The default convention is BRAND-TYPE-NNN[-OPTION], e.g. MI-JER-001-M.
 * Both the pattern and the size run are configuration, not code: pass different values from settings.
 */
final class SkuPolicy
{
    public const APPAREL_SIZES = ['S', 'M', 'L', 'XL', 'XXL', 'XXXL'];

    /**
     * @param string $rootPattern regex a product code (SKU root) must match
     * @param list<string> $sizeRun
     */
    public function __construct(
        private readonly string $rootPattern = '/^[A-Z0-9]{2,8}-[A-Z0-9]{2,8}-[0-9]{3,6}$/',
        private readonly array $sizeRun = self::APPAREL_SIZES,
        private readonly bool $requireBrandPrefix = true,
    ) {
    }

    public static function normalise(string $sku): string
    {
        return strtoupper(trim($sku));
    }

    /** @return string|null a problem description, or null when valid */
    public function checkRoot(string $root, string $brandPrefix): ?string
    {
        $root = self::normalise($root);
        if (!preg_match($this->rootPattern, $root)) {
            return "\"{$root}\" does not follow the product code format (for example {$brandPrefix}-JER-001).";
        }
        if ($this->requireBrandPrefix && !str_starts_with($root, strtoupper($brandPrefix) . '-')) {
            return "Product codes for this brand start with {$brandPrefix}-.";
        }
        return null;
    }

    public function checkSku(string $sku): ?string
    {
        $sku = self::normalise($sku);
        if (strlen($sku) < 3 || strlen($sku) > 64 || !preg_match('/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/', $sku)) {
            return "\"{$sku}\" is not a valid SKU. Use capital letters, digits and single hyphens.";
        }
        return null;
    }

    /** @return list<string> */
    public function sizeRun(): array
    {
        return $this->sizeRun;
    }

    public function optionSku(string $root, string $option): string
    {
        $suffix = preg_replace('/[^A-Z0-9]+/', '', strtoupper($option));
        if ($suffix === '') {
            throw new \InvalidArgumentException("Option \"{$option}\" cannot be turned into a SKU suffix.");
        }
        return self::normalise($root) . '-' . $suffix;
    }

    /**
     * SKUs for each option, e.g. MI-JER-001 + [S, M] => [S => MI-JER-001-S, M => MI-JER-001-M].
     * An empty option list means a product without variations: the root is the SKU.
     *
     * @param list<string> $options
     * @return array<string, string> option label => SKU ('' => root for no variations)
     */
    public function generate(string $root, array $options): array
    {
        if ($options === []) {
            return ['' => self::normalise($root)];
        }
        $out = [];
        foreach ($options as $o) {
            $label = strtoupper(trim($o));
            if (isset($out[$label])) {
                throw new \InvalidArgumentException("Option {$label} is listed twice.");
            }
            $out[$label] = $this->optionSku($root, $label);
        }
        return $out;
    }
}
