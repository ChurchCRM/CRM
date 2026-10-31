<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Base\Deposit as BaseDeposit;
use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\model\ChurchCRM\Map\DonationFundTableMap;
use ChurchCRM\model\ChurchCRM\Map\PledgeTableMap;
use ChurchCRM\model\ChurchCRM\PledgeQuery as ChildPledgeQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Skeleton subclass for representing a row from the 'deposit_dep' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class Deposit extends BaseDeposit
{
    public function preDelete(ConnectionInterface $con = null): bool
    {
        $this->getPledges()->delete();

        return true;
    }

    public function getOFX(): \stdClass
    {
        $OFXReturn = new \stdClass();
        if ($this->getPledges()->count() === 0) {
            throw new \Exception('No Payments on this Deposit', 404);
        }

        $orgName = 'ChurchCRM Deposit Data';
        $OFXReturn->content = 'OFXHEADER:100' . PHP_EOL .
            'DATA:OFXSGML' . PHP_EOL .
            'VERSION:102' . PHP_EOL .
            'SECURITY:NONE' . PHP_EOL .
            'ENCODING:USASCII' . PHP_EOL .
            'CHARSET:1252' . PHP_EOL .
            'COMPRESSION:NONE' . PHP_EOL .
            'OLDFILEUID:NONE' . PHP_EOL .
            'NEWFILEUID:NONE' . PHP_EOL . PHP_EOL;
        $OFXReturn->content .= '<OFX>';
        $OFXReturn->content .= '<SIGNONMSGSRSV1><SONRS><STATUS><CODE>0<SEVERITY>INFO</STATUS><DTSERVER>' . date('YmdHis.u[O:T]') . '<LANGUAGE>ENG<FI><ORG>' . $orgName . '<FID>12345</FI></SONRS></SIGNONMSGSRSV1>';
        $OFXReturn->content .= '<BANKMSGSRSV1>' .
            '<STMTTRNRS>' .
            '<TRNUID>' .
            '<STATUS>' .
            '<CODE>0' .
            '<SEVERITY>INFO' .
            '</STATUS>';

        foreach ($this->getFundTotals() as $fund) {
            $OFXReturn->content .= '<STMTRS>' .
                '<CURDEF>USD' .
                '<BANKACCTFROM>' .
                '<BANKID>' . $orgName .
                '<ACCTID>' . $fund['Name'] .
                '<ACCTTYPE>SAVINGS' .
                '</BANKACCTFROM>';
            $OFXReturn->content .=
                '<STMTTRN>' .
                '<TRNTYPE>CREDIT' .
                '<DTPOSTED>' . $this->getDate('Ymd') .
                '<TRNAMT>' . $fund['Total'] .
                '<FITID>' .
                '<NAME>' . $this->getComment() .
                '<MEMO>' . $fund['Name'] .
                '</STMTTRN></STMTRS>';
        }

        $OFXReturn->content .= '</STMTTRNRS></BANKTRANLIST></OFX>';
        // Export file
        $OFXReturn->header = 'Content-Disposition: attachment; filename=ChurchCRM-Deposit-' . $this->getId() . '-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.ofx';

        return $OFXReturn;
    }

    public function getTotalAmount()
    {
        return $this->getVirtualColumn('totalAmount');
    }

    public function getTotalChecks()
    {
        return PledgeQuery::create()
            ->filterByDepId($this->getId())
            ->filterByMethod('CHECK')
            ->addAsColumn('sumAmount', 'SUM(' . PledgeTableMap::COL_PLG_AMOUNT . ')')
            ->find()
            ->getColumnValues('sumAmount')[0];
    }

    public function getTotalCash()
    {
        return PledgeQuery::create()
            ->filterByDepId($this->getId())
            ->filterByMethod('CASH')
            ->addAsColumn('sumAmount', 'SUM(' . PledgeTableMap::COL_PLG_AMOUNT . ')')
            ->find()
            ->getColumnValues('sumAmount')[0];
    }

    public function getCountChecks()
    {
        return PledgeQuery::create()
            ->filterByDepId($this->getId())
            ->groupByGroupKey()
            ->filterByMethod('CHECK')
            ->find()
            ->count();
    }

    public function getCountCash()
    {
        return PledgeQuery::create()
            ->filterByDepId($this->getId())
            ->groupByGroupKey()
            ->filterByMethod('CASH')
            ->find()
            ->count();
    }

    public function getFundTotals()
    {
        return PledgeQuery::create()
        ->filterByDepId($this->getId())
        ->groupByFundId()
        ->addAsColumn('Total', 'SUM(' . PledgeTableMap::COL_PLG_AMOUNT . ')')
        ->joinDonationFund()
        ->addAsColumn('Name', DonationFundTableMap::COL_FUN_NAME)
        ->orderBy(DonationFundTableMap::COL_FUN_NAME)
        ->select(['Name', 'Total'])
        ->find();
    }

    public function getPledgesJoinAll(Criteria $criteria = null, ConnectionInterface $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildPledgeQuery::create(null, $criteria);
        $query->joinWith('Family', Criteria::RIGHT_JOIN);
        $query->joinWith('DonationFund', Criteria::RIGHT_JOIN);

        return $this->getPledges($query, $con);
    }

    /**
     * Get the previous deposit (by ID).
     */
    public static function getPreviousDeposit(int $currentId): ?Deposit
    {
        return DepositQuery::create()
            ->filterById($currentId, Criteria::LESS_THAN)
            ->orderById(Criteria::DESC)
            ->findOne();
    }

    /**
     * Get the next deposit (by ID).
     */
    public static function getNextDeposit(int $currentId): ?Deposit
    {
        return DepositQuery::create()
            ->filterById($currentId, Criteria::GREATER_THAN)
            ->orderById(Criteria::ASC)
            ->findOne();
    }
}
