<?php
/*
 * PhreeBooks support functions
 *
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.TXT.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/OSL-3.0
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade Bizuno to newer
 * versions in the future. If you wish to customize Bizuno for your
 * needs please refer to http://www.phreesoft.com for more information.
 *
 * @name       Bizuno ERP
 * @author     Dave Premo, PhreeSoft <support@phreesoft.com>
 * @copyright  2008-2019, PhreeSoft, Inc.
 * @license    http://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * @version    3.x Last Update: 2019-09-05
 * @filesource /lib/controller/module/contacts/functions.php
 */

namespace bizuno;

/**
 * Processes a value by format, used in PhreeForm
 * @global array $report - report structure
 * @param mixed $value - value to process
 * @param type $format - what to do with the value
 * @return mixed, returns $value if no formats match otherwise the formatted value
 */
function contactsProcess($value, $format = '')
{
    switch ($format) {
        case 'qtrNeg0': $range = 'q0';
        case 'qtrNeg1': if (empty($range)) { $range = 'q1'; }
        case 'qtrNeg2': if (empty($range)) { $range = 'q2'; }
        case 'qtrNeg3': if (empty($range)) { $range = 'q3'; }
        case 'qtrNeg4': if (empty($range)) { $range = 'q4'; }
        case 'qtrNeg5': if (empty($range)) { $range = 'q5'; }
            return viewContactSales($value, $range);
    }
}

/**
 * Pulls the average sales over the past 12 months of the specified SKU, with cache for multiple hits
 * @param type integer - number of sales, zero if not found or none
 */
function viewContactSales($cID='',$range='m12')
{
    if (empty($GLOBALS['contactSales'])) {
        $dates  = dbSqlDates('h'); // this quarter
        $qtrNeg0= $dates['start_date'];
        $qtrNeg1= localeCalculateDate($qtrNeg0, 0,  -3);
        $qtrNeg2= localeCalculateDate($qtrNeg1, 0,  -3);
        $qtrNeg3= localeCalculateDate($qtrNeg2, 0,  -3);
        $qtrNeg4= localeCalculateDate($qtrNeg3, 0,  -3);
        $qtrNeg5= localeCalculateDate($qtrNeg4, 0,  -3);
        $fields = ['post_date', 'journal_id', 'total_amount', 'contact_id_b'];
        $filter = "post_date >= '$qtrNeg5' AND post_date < '{$dates['end_date']}' AND journal_id IN (12,13)";
        $rows   = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', $filter, 'contact_id_b', $fields);
        foreach ($rows as $row) {
            if (empty($GLOBALS['contactSales'][$row['contact_id_b']])) { $GLOBALS['contactSales'][$row['contact_id_b']] = ['q0'=>0,'q1'=>0,'q2'=>0,'q3'=>0,'q4'=>0,'q5'=>0]; }
            if ($row['journal_id']==13)            { $row['total_amount'] = -$row['total_amount']; }
            if     ($row['post_date'] >= $qtrNeg0) { $GLOBALS['contactSales'][$row['contact_id_b']]['q0'] += $row['total_amount']; }
            elseif ($row['post_date'] >= $qtrNeg1) { $GLOBALS['contactSales'][$row['contact_id_b']]['q1'] += $row['total_amount']; }
            elseif ($row['post_date'] >= $qtrNeg2) { $GLOBALS['contactSales'][$row['contact_id_b']]['q2'] += $row['total_amount']; }
            elseif ($row['post_date'] >= $qtrNeg3) { $GLOBALS['contactSales'][$row['contact_id_b']]['q3'] += $row['total_amount']; }
            elseif ($row['post_date'] >= $qtrNeg4) { $GLOBALS['contactSales'][$row['contact_id_b']]['q4'] += $row['total_amount']; }
            else                                   { $GLOBALS['contactSales'][$row['contact_id_b']]['q5'] += $row['total_amount']; }
        }
    }
    return !empty($GLOBALS['contactSales'][$cID][$range]) ? number_format($GLOBALS['contactSales'][$cID][$range], 2) : '';
}
