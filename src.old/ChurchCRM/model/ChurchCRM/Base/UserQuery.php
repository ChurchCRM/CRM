<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\User as ChildUser;
use ChurchCRM\UserQuery as ChildUserQuery;
use ChurchCRM\Map\UserTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'user_usr' table.
 *
 *
 *
 * @method     ChildUserQuery orderByPersonId($order = Criteria::ASC) Order by the usr_per_ID column
 * @method     ChildUserQuery orderByPassword($order = Criteria::ASC) Order by the usr_Password column
 * @method     ChildUserQuery orderByNeedPasswordChange($order = Criteria::ASC) Order by the usr_NeedPasswordChange column
 * @method     ChildUserQuery orderByLastLogin($order = Criteria::ASC) Order by the usr_LastLogin column
 * @method     ChildUserQuery orderByLoginCount($order = Criteria::ASC) Order by the usr_LoginCount column
 * @method     ChildUserQuery orderByFailedLogins($order = Criteria::ASC) Order by the usr_FailedLogins column
 * @method     ChildUserQuery orderByAddRecords($order = Criteria::ASC) Order by the usr_AddRecords column
 * @method     ChildUserQuery orderByEditRecords($order = Criteria::ASC) Order by the usr_EditRecords column
 * @method     ChildUserQuery orderByDeleteRecords($order = Criteria::ASC) Order by the usr_DeleteRecords column
 * @method     ChildUserQuery orderByMenuOptions($order = Criteria::ASC) Order by the usr_MenuOptions column
 * @method     ChildUserQuery orderByManageGroups($order = Criteria::ASC) Order by the usr_ManageGroups column
 * @method     ChildUserQuery orderByFinance($order = Criteria::ASC) Order by the usr_Finance column
 * @method     ChildUserQuery orderByNotes($order = Criteria::ASC) Order by the usr_Notes column
 * @method     ChildUserQuery orderByAdmin($order = Criteria::ASC) Order by the usr_Admin column
 * @method     ChildUserQuery orderBySearchLimit($order = Criteria::ASC) Order by the usr_SearchLimit column
 * @method     ChildUserQuery orderByStyle($order = Criteria::ASC) Order by the usr_Style column
 * @method     ChildUserQuery orderByShowPledges($order = Criteria::ASC) Order by the usr_showPledges column
 * @method     ChildUserQuery orderByShowPayments($order = Criteria::ASC) Order by the usr_showPayments column
 * @method     ChildUserQuery orderByShowSince($order = Criteria::ASC) Order by the usr_showSince column
 * @method     ChildUserQuery orderByDefaultFY($order = Criteria::ASC) Order by the usr_defaultFY column
 * @method     ChildUserQuery orderByCurrentDeposit($order = Criteria::ASC) Order by the usr_currentDeposit column
 * @method     ChildUserQuery orderByUserName($order = Criteria::ASC) Order by the usr_UserName column
 * @method     ChildUserQuery orderByEditSelf($order = Criteria::ASC) Order by the usr_EditSelf column
 * @method     ChildUserQuery orderByCalStart($order = Criteria::ASC) Order by the usr_CalStart column
 * @method     ChildUserQuery orderByCalEnd($order = Criteria::ASC) Order by the usr_CalEnd column
 * @method     ChildUserQuery orderByCalNoSchool1($order = Criteria::ASC) Order by the usr_CalNoSchool1 column
 * @method     ChildUserQuery orderByCalNoSchool2($order = Criteria::ASC) Order by the usr_CalNoSchool2 column
 * @method     ChildUserQuery orderByCalNoSchool3($order = Criteria::ASC) Order by the usr_CalNoSchool3 column
 * @method     ChildUserQuery orderByCalNoSchool4($order = Criteria::ASC) Order by the usr_CalNoSchool4 column
 * @method     ChildUserQuery orderByCalNoSchool5($order = Criteria::ASC) Order by the usr_CalNoSchool5 column
 * @method     ChildUserQuery orderByCalNoSchool6($order = Criteria::ASC) Order by the usr_CalNoSchool6 column
 * @method     ChildUserQuery orderByCalNoSchool7($order = Criteria::ASC) Order by the usr_CalNoSchool7 column
 * @method     ChildUserQuery orderByCalNoSchool8($order = Criteria::ASC) Order by the usr_CalNoSchool8 column
 * @method     ChildUserQuery orderBySearchfamily($order = Criteria::ASC) Order by the usr_SearchFamily column
 * @method     ChildUserQuery orderByCanvasser($order = Criteria::ASC) Order by the usr_Canvasser column
 *
 * @method     ChildUserQuery groupByPersonId() Group by the usr_per_ID column
 * @method     ChildUserQuery groupByPassword() Group by the usr_Password column
 * @method     ChildUserQuery groupByNeedPasswordChange() Group by the usr_NeedPasswordChange column
 * @method     ChildUserQuery groupByLastLogin() Group by the usr_LastLogin column
 * @method     ChildUserQuery groupByLoginCount() Group by the usr_LoginCount column
 * @method     ChildUserQuery groupByFailedLogins() Group by the usr_FailedLogins column
 * @method     ChildUserQuery groupByAddRecords() Group by the usr_AddRecords column
 * @method     ChildUserQuery groupByEditRecords() Group by the usr_EditRecords column
 * @method     ChildUserQuery groupByDeleteRecords() Group by the usr_DeleteRecords column
 * @method     ChildUserQuery groupByMenuOptions() Group by the usr_MenuOptions column
 * @method     ChildUserQuery groupByManageGroups() Group by the usr_ManageGroups column
 * @method     ChildUserQuery groupByFinance() Group by the usr_Finance column
 * @method     ChildUserQuery groupByNotes() Group by the usr_Notes column
 * @method     ChildUserQuery groupByAdmin() Group by the usr_Admin column
 * @method     ChildUserQuery groupBySearchLimit() Group by the usr_SearchLimit column
 * @method     ChildUserQuery groupByStyle() Group by the usr_Style column
 * @method     ChildUserQuery groupByShowPledges() Group by the usr_showPledges column
 * @method     ChildUserQuery groupByShowPayments() Group by the usr_showPayments column
 * @method     ChildUserQuery groupByShowSince() Group by the usr_showSince column
 * @method     ChildUserQuery groupByDefaultFY() Group by the usr_defaultFY column
 * @method     ChildUserQuery groupByCurrentDeposit() Group by the usr_currentDeposit column
 * @method     ChildUserQuery groupByUserName() Group by the usr_UserName column
 * @method     ChildUserQuery groupByEditSelf() Group by the usr_EditSelf column
 * @method     ChildUserQuery groupByCalStart() Group by the usr_CalStart column
 * @method     ChildUserQuery groupByCalEnd() Group by the usr_CalEnd column
 * @method     ChildUserQuery groupByCalNoSchool1() Group by the usr_CalNoSchool1 column
 * @method     ChildUserQuery groupByCalNoSchool2() Group by the usr_CalNoSchool2 column
 * @method     ChildUserQuery groupByCalNoSchool3() Group by the usr_CalNoSchool3 column
 * @method     ChildUserQuery groupByCalNoSchool4() Group by the usr_CalNoSchool4 column
 * @method     ChildUserQuery groupByCalNoSchool5() Group by the usr_CalNoSchool5 column
 * @method     ChildUserQuery groupByCalNoSchool6() Group by the usr_CalNoSchool6 column
 * @method     ChildUserQuery groupByCalNoSchool7() Group by the usr_CalNoSchool7 column
 * @method     ChildUserQuery groupByCalNoSchool8() Group by the usr_CalNoSchool8 column
 * @method     ChildUserQuery groupBySearchfamily() Group by the usr_SearchFamily column
 * @method     ChildUserQuery groupByCanvasser() Group by the usr_Canvasser column
 *
 * @method     ChildUserQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildUserQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildUserQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildUserQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildUserQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildUserQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildUserQuery leftJoinPerson($relationAlias = null) Adds a LEFT JOIN clause to the query using the Person relation
 * @method     ChildUserQuery rightJoinPerson($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Person relation
 * @method     ChildUserQuery innerJoinPerson($relationAlias = null) Adds a INNER JOIN clause to the query using the Person relation
 *
 * @method     ChildUserQuery joinWithPerson($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the Person relation
 *
 * @method     ChildUserQuery leftJoinWithPerson() Adds a LEFT JOIN clause and with to the query using the Person relation
 * @method     ChildUserQuery rightJoinWithPerson() Adds a RIGHT JOIN clause and with to the query using the Person relation
 * @method     ChildUserQuery innerJoinWithPerson() Adds a INNER JOIN clause and with to the query using the Person relation
 *
 * @method     ChildUserQuery leftJoinUserConfig($relationAlias = null) Adds a LEFT JOIN clause to the query using the UserConfig relation
 * @method     ChildUserQuery rightJoinUserConfig($relationAlias = null) Adds a RIGHT JOIN clause to the query using the UserConfig relation
 * @method     ChildUserQuery innerJoinUserConfig($relationAlias = null) Adds a INNER JOIN clause to the query using the UserConfig relation
 *
 * @method     ChildUserQuery joinWithUserConfig($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the UserConfig relation
 *
 * @method     ChildUserQuery leftJoinWithUserConfig() Adds a LEFT JOIN clause and with to the query using the UserConfig relation
 * @method     ChildUserQuery rightJoinWithUserConfig() Adds a RIGHT JOIN clause and with to the query using the UserConfig relation
 * @method     ChildUserQuery innerJoinWithUserConfig() Adds a INNER JOIN clause and with to the query using the UserConfig relation
 *
 * @method     \ChurchCRM\PersonQuery|\ChurchCRM\UserConfigQuery endUse() Finalizes a secondary criteria and merges it with its primary Criteria
 *
 * @method     ChildUser findOne(ConnectionInterface $con = null) Return the first ChildUser matching the query
 * @method     ChildUser findOneOrCreate(ConnectionInterface $con = null) Return the first ChildUser matching the query, or a new ChildUser object populated from the query conditions when no match is found
 *
 * @method     ChildUser findOneByPersonId(int $usr_per_ID) Return the first ChildUser filtered by the usr_per_ID column
 * @method     ChildUser findOneByPassword(string $usr_Password) Return the first ChildUser filtered by the usr_Password column
 * @method     ChildUser findOneByNeedPasswordChange(boolean $usr_NeedPasswordChange) Return the first ChildUser filtered by the usr_NeedPasswordChange column
 * @method     ChildUser findOneByLastLogin(string $usr_LastLogin) Return the first ChildUser filtered by the usr_LastLogin column
 * @method     ChildUser findOneByLoginCount(int $usr_LoginCount) Return the first ChildUser filtered by the usr_LoginCount column
 * @method     ChildUser findOneByFailedLogins(int $usr_FailedLogins) Return the first ChildUser filtered by the usr_FailedLogins column
 * @method     ChildUser findOneByAddRecords(boolean $usr_AddRecords) Return the first ChildUser filtered by the usr_AddRecords column
 * @method     ChildUser findOneByEditRecords(boolean $usr_EditRecords) Return the first ChildUser filtered by the usr_EditRecords column
 * @method     ChildUser findOneByDeleteRecords(boolean $usr_DeleteRecords) Return the first ChildUser filtered by the usr_DeleteRecords column
 * @method     ChildUser findOneByMenuOptions(boolean $usr_MenuOptions) Return the first ChildUser filtered by the usr_MenuOptions column
 * @method     ChildUser findOneByManageGroups(boolean $usr_ManageGroups) Return the first ChildUser filtered by the usr_ManageGroups column
 * @method     ChildUser findOneByFinance(boolean $usr_Finance) Return the first ChildUser filtered by the usr_Finance column
 * @method     ChildUser findOneByNotes(boolean $usr_Notes) Return the first ChildUser filtered by the usr_Notes column
 * @method     ChildUser findOneByAdmin(boolean $usr_Admin) Return the first ChildUser filtered by the usr_Admin column
 * @method     ChildUser findOneBySearchLimit(int $usr_SearchLimit) Return the first ChildUser filtered by the usr_SearchLimit column
 * @method     ChildUser findOneByStyle(string $usr_Style) Return the first ChildUser filtered by the usr_Style column
 * @method     ChildUser findOneByShowPledges(boolean $usr_showPledges) Return the first ChildUser filtered by the usr_showPledges column
 * @method     ChildUser findOneByShowPayments(boolean $usr_showPayments) Return the first ChildUser filtered by the usr_showPayments column
 * @method     ChildUser findOneByShowSince(string $usr_showSince) Return the first ChildUser filtered by the usr_showSince column
 * @method     ChildUser findOneByDefaultFY(int $usr_defaultFY) Return the first ChildUser filtered by the usr_defaultFY column
 * @method     ChildUser findOneByCurrentDeposit(int $usr_currentDeposit) Return the first ChildUser filtered by the usr_currentDeposit column
 * @method     ChildUser findOneByUserName(string $usr_UserName) Return the first ChildUser filtered by the usr_UserName column
 * @method     ChildUser findOneByEditSelf(boolean $usr_EditSelf) Return the first ChildUser filtered by the usr_EditSelf column
 * @method     ChildUser findOneByCalStart(string $usr_CalStart) Return the first ChildUser filtered by the usr_CalStart column
 * @method     ChildUser findOneByCalEnd(string $usr_CalEnd) Return the first ChildUser filtered by the usr_CalEnd column
 * @method     ChildUser findOneByCalNoSchool1(string $usr_CalNoSchool1) Return the first ChildUser filtered by the usr_CalNoSchool1 column
 * @method     ChildUser findOneByCalNoSchool2(string $usr_CalNoSchool2) Return the first ChildUser filtered by the usr_CalNoSchool2 column
 * @method     ChildUser findOneByCalNoSchool3(string $usr_CalNoSchool3) Return the first ChildUser filtered by the usr_CalNoSchool3 column
 * @method     ChildUser findOneByCalNoSchool4(string $usr_CalNoSchool4) Return the first ChildUser filtered by the usr_CalNoSchool4 column
 * @method     ChildUser findOneByCalNoSchool5(string $usr_CalNoSchool5) Return the first ChildUser filtered by the usr_CalNoSchool5 column
 * @method     ChildUser findOneByCalNoSchool6(string $usr_CalNoSchool6) Return the first ChildUser filtered by the usr_CalNoSchool6 column
 * @method     ChildUser findOneByCalNoSchool7(string $usr_CalNoSchool7) Return the first ChildUser filtered by the usr_CalNoSchool7 column
 * @method     ChildUser findOneByCalNoSchool8(string $usr_CalNoSchool8) Return the first ChildUser filtered by the usr_CalNoSchool8 column
 * @method     ChildUser findOneBySearchfamily(int $usr_SearchFamily) Return the first ChildUser filtered by the usr_SearchFamily column
 * @method     ChildUser findOneByCanvasser(boolean $usr_Canvasser) Return the first ChildUser filtered by the usr_Canvasser column *

 * @method     ChildUser requirePk($key, ConnectionInterface $con = null) Return the ChildUser by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOne(ConnectionInterface $con = null) Return the first ChildUser matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildUser requireOneByPersonId(int $usr_per_ID) Return the first ChildUser filtered by the usr_per_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByPassword(string $usr_Password) Return the first ChildUser filtered by the usr_Password column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByNeedPasswordChange(boolean $usr_NeedPasswordChange) Return the first ChildUser filtered by the usr_NeedPasswordChange column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByLastLogin(string $usr_LastLogin) Return the first ChildUser filtered by the usr_LastLogin column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByLoginCount(int $usr_LoginCount) Return the first ChildUser filtered by the usr_LoginCount column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByFailedLogins(int $usr_FailedLogins) Return the first ChildUser filtered by the usr_FailedLogins column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByAddRecords(boolean $usr_AddRecords) Return the first ChildUser filtered by the usr_AddRecords column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByEditRecords(boolean $usr_EditRecords) Return the first ChildUser filtered by the usr_EditRecords column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByDeleteRecords(boolean $usr_DeleteRecords) Return the first ChildUser filtered by the usr_DeleteRecords column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByMenuOptions(boolean $usr_MenuOptions) Return the first ChildUser filtered by the usr_MenuOptions column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByManageGroups(boolean $usr_ManageGroups) Return the first ChildUser filtered by the usr_ManageGroups column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByFinance(boolean $usr_Finance) Return the first ChildUser filtered by the usr_Finance column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByNotes(boolean $usr_Notes) Return the first ChildUser filtered by the usr_Notes column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByAdmin(boolean $usr_Admin) Return the first ChildUser filtered by the usr_Admin column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneBySearchLimit(int $usr_SearchLimit) Return the first ChildUser filtered by the usr_SearchLimit column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByStyle(string $usr_Style) Return the first ChildUser filtered by the usr_Style column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByShowPledges(boolean $usr_showPledges) Return the first ChildUser filtered by the usr_showPledges column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByShowPayments(boolean $usr_showPayments) Return the first ChildUser filtered by the usr_showPayments column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByShowSince(string $usr_showSince) Return the first ChildUser filtered by the usr_showSince column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByDefaultFY(int $usr_defaultFY) Return the first ChildUser filtered by the usr_defaultFY column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByCurrentDeposit(int $usr_currentDeposit) Return the first ChildUser filtered by the usr_currentDeposit column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByUserName(string $usr_UserName) Return the first ChildUser filtered by the usr_UserName column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByEditSelf(boolean $usr_EditSelf) Return the first ChildUser filtered by the usr_EditSelf column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByCalStart(string $usr_CalStart) Return the first ChildUser filtered by the usr_CalStart column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByCalEnd(string $usr_CalEnd) Return the first ChildUser filtered by the usr_CalEnd column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByCalNoSchool1(string $usr_CalNoSchool1) Return the first ChildUser filtered by the usr_CalNoSchool1 column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByCalNoSchool2(string $usr_CalNoSchool2) Return the first ChildUser filtered by the usr_CalNoSchool2 column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByCalNoSchool3(string $usr_CalNoSchool3) Return the first ChildUser filtered by the usr_CalNoSchool3 column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByCalNoSchool4(string $usr_CalNoSchool4) Return the first ChildUser filtered by the usr_CalNoSchool4 column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByCalNoSchool5(string $usr_CalNoSchool5) Return the first ChildUser filtered by the usr_CalNoSchool5 column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByCalNoSchool6(string $usr_CalNoSchool6) Return the first ChildUser filtered by the usr_CalNoSchool6 column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByCalNoSchool7(string $usr_CalNoSchool7) Return the first ChildUser filtered by the usr_CalNoSchool7 column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByCalNoSchool8(string $usr_CalNoSchool8) Return the first ChildUser filtered by the usr_CalNoSchool8 column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneBySearchfamily(int $usr_SearchFamily) Return the first ChildUser filtered by the usr_SearchFamily column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUser requireOneByCanvasser(boolean $usr_Canvasser) Return the first ChildUser filtered by the usr_Canvasser column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildUser[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildUser objects based on current ModelCriteria
 * @method     ChildUser[]|ObjectCollection findByPersonId(int $usr_per_ID) Return ChildUser objects filtered by the usr_per_ID column
 * @method     ChildUser[]|ObjectCollection findByPassword(string $usr_Password) Return ChildUser objects filtered by the usr_Password column
 * @method     ChildUser[]|ObjectCollection findByNeedPasswordChange(boolean $usr_NeedPasswordChange) Return ChildUser objects filtered by the usr_NeedPasswordChange column
 * @method     ChildUser[]|ObjectCollection findByLastLogin(string $usr_LastLogin) Return ChildUser objects filtered by the usr_LastLogin column
 * @method     ChildUser[]|ObjectCollection findByLoginCount(int $usr_LoginCount) Return ChildUser objects filtered by the usr_LoginCount column
 * @method     ChildUser[]|ObjectCollection findByFailedLogins(int $usr_FailedLogins) Return ChildUser objects filtered by the usr_FailedLogins column
 * @method     ChildUser[]|ObjectCollection findByAddRecords(boolean $usr_AddRecords) Return ChildUser objects filtered by the usr_AddRecords column
 * @method     ChildUser[]|ObjectCollection findByEditRecords(boolean $usr_EditRecords) Return ChildUser objects filtered by the usr_EditRecords column
 * @method     ChildUser[]|ObjectCollection findByDeleteRecords(boolean $usr_DeleteRecords) Return ChildUser objects filtered by the usr_DeleteRecords column
 * @method     ChildUser[]|ObjectCollection findByMenuOptions(boolean $usr_MenuOptions) Return ChildUser objects filtered by the usr_MenuOptions column
 * @method     ChildUser[]|ObjectCollection findByManageGroups(boolean $usr_ManageGroups) Return ChildUser objects filtered by the usr_ManageGroups column
 * @method     ChildUser[]|ObjectCollection findByFinance(boolean $usr_Finance) Return ChildUser objects filtered by the usr_Finance column
 * @method     ChildUser[]|ObjectCollection findByNotes(boolean $usr_Notes) Return ChildUser objects filtered by the usr_Notes column
 * @method     ChildUser[]|ObjectCollection findByAdmin(boolean $usr_Admin) Return ChildUser objects filtered by the usr_Admin column
 * @method     ChildUser[]|ObjectCollection findBySearchLimit(int $usr_SearchLimit) Return ChildUser objects filtered by the usr_SearchLimit column
 * @method     ChildUser[]|ObjectCollection findByStyle(string $usr_Style) Return ChildUser objects filtered by the usr_Style column
 * @method     ChildUser[]|ObjectCollection findByShowPledges(boolean $usr_showPledges) Return ChildUser objects filtered by the usr_showPledges column
 * @method     ChildUser[]|ObjectCollection findByShowPayments(boolean $usr_showPayments) Return ChildUser objects filtered by the usr_showPayments column
 * @method     ChildUser[]|ObjectCollection findByShowSince(string $usr_showSince) Return ChildUser objects filtered by the usr_showSince column
 * @method     ChildUser[]|ObjectCollection findByDefaultFY(int $usr_defaultFY) Return ChildUser objects filtered by the usr_defaultFY column
 * @method     ChildUser[]|ObjectCollection findByCurrentDeposit(int $usr_currentDeposit) Return ChildUser objects filtered by the usr_currentDeposit column
 * @method     ChildUser[]|ObjectCollection findByUserName(string $usr_UserName) Return ChildUser objects filtered by the usr_UserName column
 * @method     ChildUser[]|ObjectCollection findByEditSelf(boolean $usr_EditSelf) Return ChildUser objects filtered by the usr_EditSelf column
 * @method     ChildUser[]|ObjectCollection findByCalStart(string $usr_CalStart) Return ChildUser objects filtered by the usr_CalStart column
 * @method     ChildUser[]|ObjectCollection findByCalEnd(string $usr_CalEnd) Return ChildUser objects filtered by the usr_CalEnd column
 * @method     ChildUser[]|ObjectCollection findByCalNoSchool1(string $usr_CalNoSchool1) Return ChildUser objects filtered by the usr_CalNoSchool1 column
 * @method     ChildUser[]|ObjectCollection findByCalNoSchool2(string $usr_CalNoSchool2) Return ChildUser objects filtered by the usr_CalNoSchool2 column
 * @method     ChildUser[]|ObjectCollection findByCalNoSchool3(string $usr_CalNoSchool3) Return ChildUser objects filtered by the usr_CalNoSchool3 column
 * @method     ChildUser[]|ObjectCollection findByCalNoSchool4(string $usr_CalNoSchool4) Return ChildUser objects filtered by the usr_CalNoSchool4 column
 * @method     ChildUser[]|ObjectCollection findByCalNoSchool5(string $usr_CalNoSchool5) Return ChildUser objects filtered by the usr_CalNoSchool5 column
 * @method     ChildUser[]|ObjectCollection findByCalNoSchool6(string $usr_CalNoSchool6) Return ChildUser objects filtered by the usr_CalNoSchool6 column
 * @method     ChildUser[]|ObjectCollection findByCalNoSchool7(string $usr_CalNoSchool7) Return ChildUser objects filtered by the usr_CalNoSchool7 column
 * @method     ChildUser[]|ObjectCollection findByCalNoSchool8(string $usr_CalNoSchool8) Return ChildUser objects filtered by the usr_CalNoSchool8 column
 * @method     ChildUser[]|ObjectCollection findBySearchfamily(int $usr_SearchFamily) Return ChildUser objects filtered by the usr_SearchFamily column
 * @method     ChildUser[]|ObjectCollection findByCanvasser(boolean $usr_Canvasser) Return ChildUser objects filtered by the usr_Canvasser column
 * @method     ChildUser[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class UserQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\UserQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\User', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildUserQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildUserQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildUserQuery) {
            return $criteria;
        }
        $query = new ChildUserQuery();
        if (null !== $modelAlias) {
            $query->setModelAlias($modelAlias);
        }
        if ($criteria instanceof Criteria) {
            $query->mergeWith($criteria);
        }

        return $query;
    }

    /**
     * Find object by primary key.
     * Propel uses the instance pool to skip the database if the object exists.
     * Go fast if the query is untouched.
     *
     * <code>
     * $obj  = $c->findPk(12, $con);
     * </code>
     *
     * @param mixed $key Primary key to use for the query
     * @param ConnectionInterface $con an optional connection object
     *
     * @return ChildUser|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(UserTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = UserTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
            // the object is already in the instance pool
            return $obj;
        }

        return $this->findPkSimple($key, $con);
    }

    /**
     * Find object by primary key using raw SQL to go fast.
     * Bypass doSelect() and the object formatter by using generated code.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     ConnectionInterface $con A connection object
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return ChildUser A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT usr_per_ID, usr_Password, usr_NeedPasswordChange, usr_LastLogin, usr_LoginCount, usr_FailedLogins, usr_AddRecords, usr_EditRecords, usr_DeleteRecords, usr_MenuOptions, usr_ManageGroups, usr_Finance, usr_Notes, usr_Admin, usr_SearchLimit, usr_Style, usr_showPledges, usr_showPayments, usr_showSince, usr_defaultFY, usr_currentDeposit, usr_UserName, usr_EditSelf, usr_CalStart, usr_CalEnd, usr_CalNoSchool1, usr_CalNoSchool2, usr_CalNoSchool3, usr_CalNoSchool4, usr_CalNoSchool5, usr_CalNoSchool6, usr_CalNoSchool7, usr_CalNoSchool8, usr_SearchFamily, usr_Canvasser FROM user_usr WHERE usr_per_ID = :p0';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), 0, $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            /** @var ChildUser $obj */
            $obj = new ChildUser();
            $obj->hydrate($row);
            UserTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
        }
        $stmt->closeCursor();

        return $obj;
    }

    /**
     * Find object by primary key.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     ConnectionInterface $con A connection object
     *
     * @return ChildUser|array|mixed the result, formatted by the current formatter
     */
    protected function findPkComplex($key, ConnectionInterface $con)
    {
        // As the query uses a PK condition, no limit(1) is necessary.
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $dataFetcher = $criteria
            ->filterByPrimaryKey($key)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->formatOne($dataFetcher);
    }

    /**
     * Find objects by primary key
     * <code>
     * $objs = $c->findPks(array(12, 56, 832), $con);
     * </code>
     * @param     array $keys Primary keys to use for the query
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return ObjectCollection|array|mixed the list of results, formatted by the current formatter
     */
    public function findPks($keys, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getReadConnection($this->getDbName());
        }
        $this->basePreSelect($con);
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $dataFetcher = $criteria
            ->filterByPrimaryKeys($keys)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->format($dataFetcher);
    }

    /**
     * Filter the query by primary key
     *
     * @param     mixed $key Primary key to use for the query
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(UserTableMap::COL_USR_PER_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(UserTableMap::COL_USR_PER_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the usr_per_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByPersonId(1234); // WHERE usr_per_ID = 1234
     * $query->filterByPersonId(array(12, 34)); // WHERE usr_per_ID IN (12, 34)
     * $query->filterByPersonId(array('min' => 12)); // WHERE usr_per_ID > 12
     * </code>
     *
     * @see       filterByPerson()
     *
     * @param     mixed $personId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByPersonId($personId = null, $comparison = null)
    {
        if (is_array($personId)) {
            $useMinMax = false;
            if (isset($personId['min'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_PER_ID, $personId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($personId['max'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_PER_ID, $personId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_PER_ID, $personId, $comparison);
    }

    /**
     * Filter the query on the usr_Password column
     *
     * Example usage:
     * <code>
     * $query->filterByPassword('fooValue');   // WHERE usr_Password = 'fooValue'
     * $query->filterByPassword('%fooValue%', Criteria::LIKE); // WHERE usr_Password LIKE '%fooValue%'
     * </code>
     *
     * @param     string $password The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByPassword($password = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($password)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_PASSWORD, $password, $comparison);
    }

    /**
     * Filter the query on the usr_NeedPasswordChange column
     *
     * Example usage:
     * <code>
     * $query->filterByNeedPasswordChange(true); // WHERE usr_NeedPasswordChange = true
     * $query->filterByNeedPasswordChange('yes'); // WHERE usr_NeedPasswordChange = true
     * </code>
     *
     * @param     boolean|string $needPasswordChange The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByNeedPasswordChange($needPasswordChange = null, $comparison = null)
    {
        if (is_string($needPasswordChange)) {
            $needPasswordChange = in_array(strtolower($needPasswordChange), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_NEEDPASSWORDCHANGE, $needPasswordChange, $comparison);
    }

    /**
     * Filter the query on the usr_LastLogin column
     *
     * Example usage:
     * <code>
     * $query->filterByLastLogin('2011-03-14'); // WHERE usr_LastLogin = '2011-03-14'
     * $query->filterByLastLogin('now'); // WHERE usr_LastLogin = '2011-03-14'
     * $query->filterByLastLogin(array('max' => 'yesterday')); // WHERE usr_LastLogin > '2011-03-13'
     * </code>
     *
     * @param     mixed $lastLogin The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByLastLogin($lastLogin = null, $comparison = null)
    {
        if (is_array($lastLogin)) {
            $useMinMax = false;
            if (isset($lastLogin['min'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_LASTLOGIN, $lastLogin['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($lastLogin['max'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_LASTLOGIN, $lastLogin['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_LASTLOGIN, $lastLogin, $comparison);
    }

    /**
     * Filter the query on the usr_LoginCount column
     *
     * Example usage:
     * <code>
     * $query->filterByLoginCount(1234); // WHERE usr_LoginCount = 1234
     * $query->filterByLoginCount(array(12, 34)); // WHERE usr_LoginCount IN (12, 34)
     * $query->filterByLoginCount(array('min' => 12)); // WHERE usr_LoginCount > 12
     * </code>
     *
     * @param     mixed $loginCount The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByLoginCount($loginCount = null, $comparison = null)
    {
        if (is_array($loginCount)) {
            $useMinMax = false;
            if (isset($loginCount['min'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_LOGINCOUNT, $loginCount['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($loginCount['max'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_LOGINCOUNT, $loginCount['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_LOGINCOUNT, $loginCount, $comparison);
    }

    /**
     * Filter the query on the usr_FailedLogins column
     *
     * Example usage:
     * <code>
     * $query->filterByFailedLogins(1234); // WHERE usr_FailedLogins = 1234
     * $query->filterByFailedLogins(array(12, 34)); // WHERE usr_FailedLogins IN (12, 34)
     * $query->filterByFailedLogins(array('min' => 12)); // WHERE usr_FailedLogins > 12
     * </code>
     *
     * @param     mixed $failedLogins The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByFailedLogins($failedLogins = null, $comparison = null)
    {
        if (is_array($failedLogins)) {
            $useMinMax = false;
            if (isset($failedLogins['min'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_FAILEDLOGINS, $failedLogins['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($failedLogins['max'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_FAILEDLOGINS, $failedLogins['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_FAILEDLOGINS, $failedLogins, $comparison);
    }

    /**
     * Filter the query on the usr_AddRecords column
     *
     * Example usage:
     * <code>
     * $query->filterByAddRecords(true); // WHERE usr_AddRecords = true
     * $query->filterByAddRecords('yes'); // WHERE usr_AddRecords = true
     * </code>
     *
     * @param     boolean|string $addRecords The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByAddRecords($addRecords = null, $comparison = null)
    {
        if (is_string($addRecords)) {
            $addRecords = in_array(strtolower($addRecords), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_ADDRECORDS, $addRecords, $comparison);
    }

    /**
     * Filter the query on the usr_EditRecords column
     *
     * Example usage:
     * <code>
     * $query->filterByEditRecords(true); // WHERE usr_EditRecords = true
     * $query->filterByEditRecords('yes'); // WHERE usr_EditRecords = true
     * </code>
     *
     * @param     boolean|string $editRecords The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByEditRecords($editRecords = null, $comparison = null)
    {
        if (is_string($editRecords)) {
            $editRecords = in_array(strtolower($editRecords), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_EDITRECORDS, $editRecords, $comparison);
    }

    /**
     * Filter the query on the usr_DeleteRecords column
     *
     * Example usage:
     * <code>
     * $query->filterByDeleteRecords(true); // WHERE usr_DeleteRecords = true
     * $query->filterByDeleteRecords('yes'); // WHERE usr_DeleteRecords = true
     * </code>
     *
     * @param     boolean|string $deleteRecords The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByDeleteRecords($deleteRecords = null, $comparison = null)
    {
        if (is_string($deleteRecords)) {
            $deleteRecords = in_array(strtolower($deleteRecords), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_DELETERECORDS, $deleteRecords, $comparison);
    }

    /**
     * Filter the query on the usr_MenuOptions column
     *
     * Example usage:
     * <code>
     * $query->filterByMenuOptions(true); // WHERE usr_MenuOptions = true
     * $query->filterByMenuOptions('yes'); // WHERE usr_MenuOptions = true
     * </code>
     *
     * @param     boolean|string $menuOptions The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByMenuOptions($menuOptions = null, $comparison = null)
    {
        if (is_string($menuOptions)) {
            $menuOptions = in_array(strtolower($menuOptions), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_MENUOPTIONS, $menuOptions, $comparison);
    }

    /**
     * Filter the query on the usr_ManageGroups column
     *
     * Example usage:
     * <code>
     * $query->filterByManageGroups(true); // WHERE usr_ManageGroups = true
     * $query->filterByManageGroups('yes'); // WHERE usr_ManageGroups = true
     * </code>
     *
     * @param     boolean|string $manageGroups The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByManageGroups($manageGroups = null, $comparison = null)
    {
        if (is_string($manageGroups)) {
            $manageGroups = in_array(strtolower($manageGroups), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_MANAGEGROUPS, $manageGroups, $comparison);
    }

    /**
     * Filter the query on the usr_Finance column
     *
     * Example usage:
     * <code>
     * $query->filterByFinance(true); // WHERE usr_Finance = true
     * $query->filterByFinance('yes'); // WHERE usr_Finance = true
     * </code>
     *
     * @param     boolean|string $finance The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByFinance($finance = null, $comparison = null)
    {
        if (is_string($finance)) {
            $finance = in_array(strtolower($finance), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_FINANCE, $finance, $comparison);
    }

    /**
     * Filter the query on the usr_Notes column
     *
     * Example usage:
     * <code>
     * $query->filterByNotes(true); // WHERE usr_Notes = true
     * $query->filterByNotes('yes'); // WHERE usr_Notes = true
     * </code>
     *
     * @param     boolean|string $notes The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByNotes($notes = null, $comparison = null)
    {
        if (is_string($notes)) {
            $notes = in_array(strtolower($notes), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_NOTES, $notes, $comparison);
    }

    /**
     * Filter the query on the usr_Admin column
     *
     * Example usage:
     * <code>
     * $query->filterByAdmin(true); // WHERE usr_Admin = true
     * $query->filterByAdmin('yes'); // WHERE usr_Admin = true
     * </code>
     *
     * @param     boolean|string $admin The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByAdmin($admin = null, $comparison = null)
    {
        if (is_string($admin)) {
            $admin = in_array(strtolower($admin), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_ADMIN, $admin, $comparison);
    }

    /**
     * Filter the query on the usr_SearchLimit column
     *
     * Example usage:
     * <code>
     * $query->filterBySearchLimit(1234); // WHERE usr_SearchLimit = 1234
     * $query->filterBySearchLimit(array(12, 34)); // WHERE usr_SearchLimit IN (12, 34)
     * $query->filterBySearchLimit(array('min' => 12)); // WHERE usr_SearchLimit > 12
     * </code>
     *
     * @param     mixed $searchLimit The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterBySearchLimit($searchLimit = null, $comparison = null)
    {
        if (is_array($searchLimit)) {
            $useMinMax = false;
            if (isset($searchLimit['min'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_SEARCHLIMIT, $searchLimit['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($searchLimit['max'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_SEARCHLIMIT, $searchLimit['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_SEARCHLIMIT, $searchLimit, $comparison);
    }

    /**
     * Filter the query on the usr_Style column
     *
     * Example usage:
     * <code>
     * $query->filterByStyle('fooValue');   // WHERE usr_Style = 'fooValue'
     * $query->filterByStyle('%fooValue%', Criteria::LIKE); // WHERE usr_Style LIKE '%fooValue%'
     * </code>
     *
     * @param     string $style The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByStyle($style = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($style)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_STYLE, $style, $comparison);
    }

    /**
     * Filter the query on the usr_showPledges column
     *
     * Example usage:
     * <code>
     * $query->filterByShowPledges(true); // WHERE usr_showPledges = true
     * $query->filterByShowPledges('yes'); // WHERE usr_showPledges = true
     * </code>
     *
     * @param     boolean|string $showPledges The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByShowPledges($showPledges = null, $comparison = null)
    {
        if (is_string($showPledges)) {
            $showPledges = in_array(strtolower($showPledges), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_SHOWPLEDGES, $showPledges, $comparison);
    }

    /**
     * Filter the query on the usr_showPayments column
     *
     * Example usage:
     * <code>
     * $query->filterByShowPayments(true); // WHERE usr_showPayments = true
     * $query->filterByShowPayments('yes'); // WHERE usr_showPayments = true
     * </code>
     *
     * @param     boolean|string $showPayments The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByShowPayments($showPayments = null, $comparison = null)
    {
        if (is_string($showPayments)) {
            $showPayments = in_array(strtolower($showPayments), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_SHOWPAYMENTS, $showPayments, $comparison);
    }

    /**
     * Filter the query on the usr_showSince column
     *
     * Example usage:
     * <code>
     * $query->filterByShowSince('2011-03-14'); // WHERE usr_showSince = '2011-03-14'
     * $query->filterByShowSince('now'); // WHERE usr_showSince = '2011-03-14'
     * $query->filterByShowSince(array('max' => 'yesterday')); // WHERE usr_showSince > '2011-03-13'
     * </code>
     *
     * @param     mixed $showSince The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByShowSince($showSince = null, $comparison = null)
    {
        if (is_array($showSince)) {
            $useMinMax = false;
            if (isset($showSince['min'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_SHOWSINCE, $showSince['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($showSince['max'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_SHOWSINCE, $showSince['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_SHOWSINCE, $showSince, $comparison);
    }

    /**
     * Filter the query on the usr_defaultFY column
     *
     * Example usage:
     * <code>
     * $query->filterByDefaultFY(1234); // WHERE usr_defaultFY = 1234
     * $query->filterByDefaultFY(array(12, 34)); // WHERE usr_defaultFY IN (12, 34)
     * $query->filterByDefaultFY(array('min' => 12)); // WHERE usr_defaultFY > 12
     * </code>
     *
     * @param     mixed $defaultFY The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByDefaultFY($defaultFY = null, $comparison = null)
    {
        if (is_array($defaultFY)) {
            $useMinMax = false;
            if (isset($defaultFY['min'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_DEFAULTFY, $defaultFY['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($defaultFY['max'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_DEFAULTFY, $defaultFY['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_DEFAULTFY, $defaultFY, $comparison);
    }

    /**
     * Filter the query on the usr_currentDeposit column
     *
     * Example usage:
     * <code>
     * $query->filterByCurrentDeposit(1234); // WHERE usr_currentDeposit = 1234
     * $query->filterByCurrentDeposit(array(12, 34)); // WHERE usr_currentDeposit IN (12, 34)
     * $query->filterByCurrentDeposit(array('min' => 12)); // WHERE usr_currentDeposit > 12
     * </code>
     *
     * @param     mixed $currentDeposit The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByCurrentDeposit($currentDeposit = null, $comparison = null)
    {
        if (is_array($currentDeposit)) {
            $useMinMax = false;
            if (isset($currentDeposit['min'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_CURRENTDEPOSIT, $currentDeposit['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($currentDeposit['max'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_CURRENTDEPOSIT, $currentDeposit['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_CURRENTDEPOSIT, $currentDeposit, $comparison);
    }

    /**
     * Filter the query on the usr_UserName column
     *
     * Example usage:
     * <code>
     * $query->filterByUserName('fooValue');   // WHERE usr_UserName = 'fooValue'
     * $query->filterByUserName('%fooValue%', Criteria::LIKE); // WHERE usr_UserName LIKE '%fooValue%'
     * </code>
     *
     * @param     string $userName The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByUserName($userName = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($userName)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_USERNAME, $userName, $comparison);
    }

    /**
     * Filter the query on the usr_EditSelf column
     *
     * Example usage:
     * <code>
     * $query->filterByEditSelf(true); // WHERE usr_EditSelf = true
     * $query->filterByEditSelf('yes'); // WHERE usr_EditSelf = true
     * </code>
     *
     * @param     boolean|string $editSelf The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByEditSelf($editSelf = null, $comparison = null)
    {
        if (is_string($editSelf)) {
            $editSelf = in_array(strtolower($editSelf), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_EDITSELF, $editSelf, $comparison);
    }

    /**
     * Filter the query on the usr_CalStart column
     *
     * Example usage:
     * <code>
     * $query->filterByCalStart('2011-03-14'); // WHERE usr_CalStart = '2011-03-14'
     * $query->filterByCalStart('now'); // WHERE usr_CalStart = '2011-03-14'
     * $query->filterByCalStart(array('max' => 'yesterday')); // WHERE usr_CalStart > '2011-03-13'
     * </code>
     *
     * @param     mixed $calStart The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByCalStart($calStart = null, $comparison = null)
    {
        if (is_array($calStart)) {
            $useMinMax = false;
            if (isset($calStart['min'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_CALSTART, $calStart['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($calStart['max'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_CALSTART, $calStart['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_CALSTART, $calStart, $comparison);
    }

    /**
     * Filter the query on the usr_CalEnd column
     *
     * Example usage:
     * <code>
     * $query->filterByCalEnd('2011-03-14'); // WHERE usr_CalEnd = '2011-03-14'
     * $query->filterByCalEnd('now'); // WHERE usr_CalEnd = '2011-03-14'
     * $query->filterByCalEnd(array('max' => 'yesterday')); // WHERE usr_CalEnd > '2011-03-13'
     * </code>
     *
     * @param     mixed $calEnd The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByCalEnd($calEnd = null, $comparison = null)
    {
        if (is_array($calEnd)) {
            $useMinMax = false;
            if (isset($calEnd['min'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_CALEND, $calEnd['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($calEnd['max'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_CALEND, $calEnd['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_CALEND, $calEnd, $comparison);
    }

    /**
     * Filter the query on the usr_CalNoSchool1 column
     *
     * Example usage:
     * <code>
     * $query->filterByCalNoSchool1('2011-03-14'); // WHERE usr_CalNoSchool1 = '2011-03-14'
     * $query->filterByCalNoSchool1('now'); // WHERE usr_CalNoSchool1 = '2011-03-14'
     * $query->filterByCalNoSchool1(array('max' => 'yesterday')); // WHERE usr_CalNoSchool1 > '2011-03-13'
     * </code>
     *
     * @param     mixed $calNoSchool1 The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByCalNoSchool1($calNoSchool1 = null, $comparison = null)
    {
        if (is_array($calNoSchool1)) {
            $useMinMax = false;
            if (isset($calNoSchool1['min'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL1, $calNoSchool1['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($calNoSchool1['max'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL1, $calNoSchool1['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL1, $calNoSchool1, $comparison);
    }

    /**
     * Filter the query on the usr_CalNoSchool2 column
     *
     * Example usage:
     * <code>
     * $query->filterByCalNoSchool2('2011-03-14'); // WHERE usr_CalNoSchool2 = '2011-03-14'
     * $query->filterByCalNoSchool2('now'); // WHERE usr_CalNoSchool2 = '2011-03-14'
     * $query->filterByCalNoSchool2(array('max' => 'yesterday')); // WHERE usr_CalNoSchool2 > '2011-03-13'
     * </code>
     *
     * @param     mixed $calNoSchool2 The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByCalNoSchool2($calNoSchool2 = null, $comparison = null)
    {
        if (is_array($calNoSchool2)) {
            $useMinMax = false;
            if (isset($calNoSchool2['min'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL2, $calNoSchool2['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($calNoSchool2['max'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL2, $calNoSchool2['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL2, $calNoSchool2, $comparison);
    }

    /**
     * Filter the query on the usr_CalNoSchool3 column
     *
     * Example usage:
     * <code>
     * $query->filterByCalNoSchool3('2011-03-14'); // WHERE usr_CalNoSchool3 = '2011-03-14'
     * $query->filterByCalNoSchool3('now'); // WHERE usr_CalNoSchool3 = '2011-03-14'
     * $query->filterByCalNoSchool3(array('max' => 'yesterday')); // WHERE usr_CalNoSchool3 > '2011-03-13'
     * </code>
     *
     * @param     mixed $calNoSchool3 The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByCalNoSchool3($calNoSchool3 = null, $comparison = null)
    {
        if (is_array($calNoSchool3)) {
            $useMinMax = false;
            if (isset($calNoSchool3['min'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL3, $calNoSchool3['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($calNoSchool3['max'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL3, $calNoSchool3['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL3, $calNoSchool3, $comparison);
    }

    /**
     * Filter the query on the usr_CalNoSchool4 column
     *
     * Example usage:
     * <code>
     * $query->filterByCalNoSchool4('2011-03-14'); // WHERE usr_CalNoSchool4 = '2011-03-14'
     * $query->filterByCalNoSchool4('now'); // WHERE usr_CalNoSchool4 = '2011-03-14'
     * $query->filterByCalNoSchool4(array('max' => 'yesterday')); // WHERE usr_CalNoSchool4 > '2011-03-13'
     * </code>
     *
     * @param     mixed $calNoSchool4 The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByCalNoSchool4($calNoSchool4 = null, $comparison = null)
    {
        if (is_array($calNoSchool4)) {
            $useMinMax = false;
            if (isset($calNoSchool4['min'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL4, $calNoSchool4['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($calNoSchool4['max'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL4, $calNoSchool4['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL4, $calNoSchool4, $comparison);
    }

    /**
     * Filter the query on the usr_CalNoSchool5 column
     *
     * Example usage:
     * <code>
     * $query->filterByCalNoSchool5('2011-03-14'); // WHERE usr_CalNoSchool5 = '2011-03-14'
     * $query->filterByCalNoSchool5('now'); // WHERE usr_CalNoSchool5 = '2011-03-14'
     * $query->filterByCalNoSchool5(array('max' => 'yesterday')); // WHERE usr_CalNoSchool5 > '2011-03-13'
     * </code>
     *
     * @param     mixed $calNoSchool5 The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByCalNoSchool5($calNoSchool5 = null, $comparison = null)
    {
        if (is_array($calNoSchool5)) {
            $useMinMax = false;
            if (isset($calNoSchool5['min'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL5, $calNoSchool5['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($calNoSchool5['max'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL5, $calNoSchool5['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL5, $calNoSchool5, $comparison);
    }

    /**
     * Filter the query on the usr_CalNoSchool6 column
     *
     * Example usage:
     * <code>
     * $query->filterByCalNoSchool6('2011-03-14'); // WHERE usr_CalNoSchool6 = '2011-03-14'
     * $query->filterByCalNoSchool6('now'); // WHERE usr_CalNoSchool6 = '2011-03-14'
     * $query->filterByCalNoSchool6(array('max' => 'yesterday')); // WHERE usr_CalNoSchool6 > '2011-03-13'
     * </code>
     *
     * @param     mixed $calNoSchool6 The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByCalNoSchool6($calNoSchool6 = null, $comparison = null)
    {
        if (is_array($calNoSchool6)) {
            $useMinMax = false;
            if (isset($calNoSchool6['min'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL6, $calNoSchool6['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($calNoSchool6['max'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL6, $calNoSchool6['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL6, $calNoSchool6, $comparison);
    }

    /**
     * Filter the query on the usr_CalNoSchool7 column
     *
     * Example usage:
     * <code>
     * $query->filterByCalNoSchool7('2011-03-14'); // WHERE usr_CalNoSchool7 = '2011-03-14'
     * $query->filterByCalNoSchool7('now'); // WHERE usr_CalNoSchool7 = '2011-03-14'
     * $query->filterByCalNoSchool7(array('max' => 'yesterday')); // WHERE usr_CalNoSchool7 > '2011-03-13'
     * </code>
     *
     * @param     mixed $calNoSchool7 The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByCalNoSchool7($calNoSchool7 = null, $comparison = null)
    {
        if (is_array($calNoSchool7)) {
            $useMinMax = false;
            if (isset($calNoSchool7['min'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL7, $calNoSchool7['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($calNoSchool7['max'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL7, $calNoSchool7['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL7, $calNoSchool7, $comparison);
    }

    /**
     * Filter the query on the usr_CalNoSchool8 column
     *
     * Example usage:
     * <code>
     * $query->filterByCalNoSchool8('2011-03-14'); // WHERE usr_CalNoSchool8 = '2011-03-14'
     * $query->filterByCalNoSchool8('now'); // WHERE usr_CalNoSchool8 = '2011-03-14'
     * $query->filterByCalNoSchool8(array('max' => 'yesterday')); // WHERE usr_CalNoSchool8 > '2011-03-13'
     * </code>
     *
     * @param     mixed $calNoSchool8 The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByCalNoSchool8($calNoSchool8 = null, $comparison = null)
    {
        if (is_array($calNoSchool8)) {
            $useMinMax = false;
            if (isset($calNoSchool8['min'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL8, $calNoSchool8['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($calNoSchool8['max'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL8, $calNoSchool8['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_CALNOSCHOOL8, $calNoSchool8, $comparison);
    }

    /**
     * Filter the query on the usr_SearchFamily column
     *
     * Example usage:
     * <code>
     * $query->filterBySearchfamily(1234); // WHERE usr_SearchFamily = 1234
     * $query->filterBySearchfamily(array(12, 34)); // WHERE usr_SearchFamily IN (12, 34)
     * $query->filterBySearchfamily(array('min' => 12)); // WHERE usr_SearchFamily > 12
     * </code>
     *
     * @param     mixed $searchfamily The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterBySearchfamily($searchfamily = null, $comparison = null)
    {
        if (is_array($searchfamily)) {
            $useMinMax = false;
            if (isset($searchfamily['min'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_SEARCHFAMILY, $searchfamily['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($searchfamily['max'])) {
                $this->addUsingAlias(UserTableMap::COL_USR_SEARCHFAMILY, $searchfamily['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_SEARCHFAMILY, $searchfamily, $comparison);
    }

    /**
     * Filter the query on the usr_Canvasser column
     *
     * Example usage:
     * <code>
     * $query->filterByCanvasser(true); // WHERE usr_Canvasser = true
     * $query->filterByCanvasser('yes'); // WHERE usr_Canvasser = true
     * </code>
     *
     * @param     boolean|string $canvasser The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function filterByCanvasser($canvasser = null, $comparison = null)
    {
        if (is_string($canvasser)) {
            $canvasser = in_array(strtolower($canvasser), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(UserTableMap::COL_USR_CANVASSER, $canvasser, $comparison);
    }

    /**
     * Filter the query by a related \ChurchCRM\Person object
     *
     * @param \ChurchCRM\Person|ObjectCollection $person The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return ChildUserQuery The current query, for fluid interface
     */
    public function filterByPerson($person, $comparison = null)
    {
        if ($person instanceof \ChurchCRM\Person) {
            return $this
                ->addUsingAlias(UserTableMap::COL_USR_PER_ID, $person->getId(), $comparison);
        } elseif ($person instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(UserTableMap::COL_USR_PER_ID, $person->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByPerson() only accepts arguments of type \ChurchCRM\Person or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Person relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function joinPerson($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Person');

        // create a ModelJoin object for this join
        $join = new ModelJoin();
        $join->setJoinType($joinType);
        $join->setRelationMap($relationMap, $this->useAliasInSQL ? $this->getModelAlias() : null, $relationAlias);
        if ($previousJoin = $this->getPreviousJoin()) {
            $join->setPreviousJoin($previousJoin);
        }

        // add the ModelJoin to the current object
        if ($relationAlias) {
            $this->addAlias($relationAlias, $relationMap->getRightTable()->getName());
            $this->addJoinObject($join, $relationAlias);
        } else {
            $this->addJoinObject($join, 'Person');
        }

        return $this;
    }

    /**
     * Use the Person relation Person object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return \ChurchCRM\PersonQuery A secondary query class using the current class as primary query
     */
    public function usePersonQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinPerson($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Person', '\ChurchCRM\PersonQuery');
    }

    /**
     * Filter the query by a related \ChurchCRM\UserConfig object
     *
     * @param \ChurchCRM\UserConfig|ObjectCollection $userConfig the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildUserQuery The current query, for fluid interface
     */
    public function filterByUserConfig($userConfig, $comparison = null)
    {
        if ($userConfig instanceof \ChurchCRM\UserConfig) {
            return $this
                ->addUsingAlias(UserTableMap::COL_USR_PER_ID, $userConfig->getPeronId(), $comparison);
        } elseif ($userConfig instanceof ObjectCollection) {
            return $this
                ->useUserConfigQuery()
                ->filterByPrimaryKeys($userConfig->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByUserConfig() only accepts arguments of type \ChurchCRM\UserConfig or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the UserConfig relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function joinUserConfig($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('UserConfig');

        // create a ModelJoin object for this join
        $join = new ModelJoin();
        $join->setJoinType($joinType);
        $join->setRelationMap($relationMap, $this->useAliasInSQL ? $this->getModelAlias() : null, $relationAlias);
        if ($previousJoin = $this->getPreviousJoin()) {
            $join->setPreviousJoin($previousJoin);
        }

        // add the ModelJoin to the current object
        if ($relationAlias) {
            $this->addAlias($relationAlias, $relationMap->getRightTable()->getName());
            $this->addJoinObject($join, $relationAlias);
        } else {
            $this->addJoinObject($join, 'UserConfig');
        }

        return $this;
    }

    /**
     * Use the UserConfig relation UserConfig object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return \ChurchCRM\UserConfigQuery A secondary query class using the current class as primary query
     */
    public function useUserConfigQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinUserConfig($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'UserConfig', '\ChurchCRM\UserConfigQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildUser $user Object to remove from the list of results
     *
     * @return $this|ChildUserQuery The current query, for fluid interface
     */
    public function prune($user = null)
    {
        if ($user) {
            $this->addUsingAlias(UserTableMap::COL_USR_PER_ID, $user->getPersonId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the user_usr table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(UserTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            UserTableMap::clearInstancePool();
            UserTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

    /**
     * Performs a DELETE on the database based on the current ModelCriteria
     *
     * @param ConnectionInterface $con the connection to use
     * @return int             The number of affected rows (if supported by underlying database driver).  This includes CASCADE-related rows
     *                         if supported by native driver or if emulated using Propel.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public function delete(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(UserTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(UserTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            UserTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            UserTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // UserQuery
