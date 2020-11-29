<?php
//Include the function library
require 'Include/Config.php';
// require 'Include/Functions.php';
// use ChurchCRM\DepositQuery;
// use ChurchCRM\Service\DashboardService;
// use ChurchCRM\dto\SystemURLs;
// use ChurchCRM\dto\SystemConfig;
// use ChurchCRM\dto\ChurchMetaData;
// use ChurchCRM\dto\MenuEventsCount;

// $dashboardService = new DashboardService();

// //last Edited members from Active families
// $updatedMembers = $dashboardService->getUpdatedMembers(12);
// //Newly added members from Active families
// $latestMembers = $dashboardService->getLatestMembers(12);

// $depositData = false;  //Determine whether or not we should display the deposit line graph
// if ($_SESSION['user']->isFinanceEnabled()) {
//     $deposits = DepositQuery::create()->useContribQuery()->useContribSplitQuery()->withColumn('SUM(contrib_split.spl_Amount)', 'totalAmount')->endUse()->endUse()->filterByDate(['min' =>date('Y-m-d', strtotime('-90 days'))])->find();
//     if (count($deposits) > 0) {
//         $depositData = $deposits->toJSON();
//     }
// }


// // Set the page title
// $sPageTitle = gettext('Welcome to').' '. ChurchMetaData::getChurchName();

// require 'Include/Header.php';

// $showBanner = SystemConfig::getValue("bEventsOnDashboardPresence");

// $peopleWithBirthDays = MenuEventsCount::getBirthDates();
// $Anniversaries = MenuEventsCount::getAnniversaries();
// $peopleWithBirthDaysCount = MenuEventsCount::getNumberBirthDates();
// $AnniversariesCount = MenuEventsCount::getNumberAnniversaries();


// if ($showBanner && ($peopleWithBirthDaysCount > 0 || $AnniversariesCount > 0)) {
//     ?>
//     <div class="alert alert-info alert-dismissible bg-purple disabled color-palette" id="Menu_Banner">
//     <button type="button" class="close" data-dismiss="alert" aria-hidden="true" style="color:#fff;">&times;</button>

//     <?php
//     if ($peopleWithBirthDaysCount > 0) {
//         ?>
//         <h4 class="alert-heading"><?= gettext("Birthdates of the day") ?></h4>
//         <p>
//         <div class="row">

//       <?php
//         $new_row = false;
//         $count_people = 0;

//         {
//             foreach ($peopleWithBirthDays as $peopleWithBirthDay) {
//                 if ($new_row == false) {
//                     ?>

//                     <div class="row">
//                 <?php
//                     $new_row = true;
//                 } ?>
//                 <div class="col-sm-3">
//                 <label class="checkbox-inline">
//                     <a href="<?= $peopleWithBirthDay->getViewURI()?>" class="btn btn-link" style="text-decoration: none"><?= $peopleWithBirthDay->getFullNameWithAge() ?></a>
//                 </label>
//                 </div>
//               <?php
//                 $count_people+=1;
//                 $count_people%=4;
//                 if ($count_people == 0) {
//                     ?>
//                     </div>
//                     <?php $new_row = false;
//                 }
//             }

//             if ($new_row == true) {
//                 ?>
//                 </div>
//             <?php
//             }
//           } ?>

//         </div>
//         </p>
//     <?php
//     } ?>

//     <?php if ($AnniversariesCount > 0) {
//         if ($peopleWithBirthDaysCount > 0) {
//             ?>
//             <hr>
//     <?php
//         } ?>

//         <h4 class="alert-heading"><?= gettext("Anniversaries of the day")?></h4>
//         <p>
//         <div class="row">

//     <?php
//         $new_row = false;
//         $count_people = 0;

//         foreach ($Anniversaries as $Anniversary) {
//             if ($new_row == false) {
//                 ?>
//                 <div class="row">

use ChurchCRM\Utils\RedirectUtils;

RedirectUtils::Redirect('v2/dashboard');
