<?php

namespace ChurchCRM\dto;

/**
 * Read-only value object that collects all SystemConfig values needed by
 * PDF report templates (letterhead, tax-report text, paper format, etc.).
 *
 * Modelled after {@see ChurchMetaData} — instantiate once per request and
 * pass the relevant array to the Twig template via {@see toLetterheadArray()}
 * or {@see toTaxReportArray()}.
 *
 * This avoids scattering dozens of SystemConfig::getValue() calls across
 * every report route handler.
 */
class ReportConfig
{
    // ── Church identity (letterhead) ────────────────────────────────────
    public readonly string $churchName;
    public readonly string $churchAddress;
    public readonly string $churchCity;
    public readonly string $churchState;
    public readonly string $churchZip;
    public readonly string $churchPhone;
    public readonly string $churchEmail;
    public readonly string $letterheadImagePath;
    public readonly string $defaultCountry;
    public readonly string $todayFormatted;

    // ── Tax report text ─────────────────────────────────────────────────
    public readonly string $taxReport1;
    public readonly string $taxReport2;
    public readonly string $taxReport3;
    public readonly string $confirmSincerely;
    public readonly string $taxSigner;

    // ── Report behaviour ────────────────────────────────────────────────
    public readonly bool   $useDonationEnvelopes;
    public readonly string $dateFilenameFormat;

    public function __construct()
    {
        $this->churchName          = (string) SystemConfig::getValue('sChurchName');
        $this->churchAddress       = (string) SystemConfig::getValue('sChurchAddress');
        $this->churchCity          = (string) SystemConfig::getValue('sChurchCity');
        $this->churchState         = (string) SystemConfig::getValue('sChurchState');
        $this->churchZip           = (string) SystemConfig::getValue('sChurchZip');
        $this->churchPhone         = (string) SystemConfig::getValue('sChurchPhone');
        $this->churchEmail         = (string) SystemConfig::getValue('sChurchEmail');
        $this->letterheadImagePath = (string) SystemConfig::getValue('bDirLetterHead');
        $this->defaultCountry      = (string) SystemConfig::getValue('sDefaultCountry');
        $this->todayFormatted      = date((string) SystemConfig::getValue('sDateFormatLong'));

        $this->taxReport1          = (string) SystemConfig::getValue('sTaxReport1');
        $this->taxReport2          = (string) SystemConfig::getValue('sTaxReport2');
        $this->taxReport3          = (string) SystemConfig::getValue('sTaxReport3');
        $this->confirmSincerely    = (string) SystemConfig::getValue('sConfirmSincerely');
        $this->taxSigner           = (string) SystemConfig::getValue('sTaxSigner');

        $this->useDonationEnvelopes = SystemConfig::getBooleanValue('bUseDonationEnvelopes');
        $this->dateFilenameFormat   = (string) SystemConfig::getValue('sDateFilenameFormat');
    }

    /**
     * Variables expected by _letter.html.twig and any letter-style report.
     *
     * @return array<string, string>
     */
    public function toLetterheadArray(): array
    {
        return [
            'churchName'      => $this->churchName,
            'churchAddress'   => $this->churchAddress,
            'churchCity'      => $this->churchCity,
            'churchState'     => $this->churchState,
            'churchZip'       => $this->churchZip,
            'churchPhone'     => $this->churchPhone,
            'churchEmail'     => $this->churchEmail,
            'letterheadImage' => $this->letterheadImagePath,
            'defaultCountry'  => $this->defaultCountry,
            'today'           => $this->todayFormatted,
        ];
    }

    /**
     * Letterhead variables + tax-report-specific text.
     * Ready to merge into the template data array for tax-report.html.twig.
     *
     * @return array<string, string>
     */
    public function toTaxReportArray(): array
    {
        return array_merge($this->toLetterheadArray(), [
            'taxReport1'       => $this->taxReport1,
            'taxReport2'       => $this->taxReport2,
            'taxReport3'       => $this->taxReport3,
            'confirmSincerely' => $this->confirmSincerely,
            'taxSigner'        => $this->taxSigner,
        ]);
    }
}
