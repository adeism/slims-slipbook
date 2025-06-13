<?php
/* Bibliography label printing */

// Debug error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// key to authenticate
defined('INDEX_AUTH') or die('Direct access is not allowed!');

// start the session
require SB.'admin/default/session.inc.php';
require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO.'simbio_DB/datagrid/simbio_dbgrid.inc.php';

// global database object
global $dbs;

// Ensure database connection is available
if (!isset($dbs) || !$dbs) {
    utility::jsToastr('Labels Printing', __('Database connection error!'), 'error');
    die('<div class="errorBox">Database connection not available</div>');
}

// privileges checking
$can_read = utility::havePrivilege('bibliography', 'r');

if (!$can_read) {
    die('<div class="errorBox">'.__('You are not authorized to view this section').'</div>');
}

$max_print = 50;
$print_count_item = 0;
$print_count_biblio = 0;

/* RECORD OPERATION */
if (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) {
    if (!$can_read) {
        die();
    }
    if (!is_array($_POST['itemID'])) {
        // make an array
        $_POST['itemID'] = array($_POST['itemID']);
    }
    /* LABEL SESSION ADDING PROCESS */
    if (!isset($_SESSION['slipbook'])) {
        $_SESSION['slipbook'] = array();
    }
    $print_count = count($_SESSION['slipbook']);
    
    // loop array
    foreach ($_POST['itemID'] as $itemID) {
        if ($print_count == $max_print) {
            $limit_reach = true;
            break;
        }

        $_SESSION['slipbook'][$itemID] = $itemID;
        $print_count++;
    }
    
    echo '<script type="text/javascript">top.$(\'#queueCount\').html(\''.count($_SESSION['slipbook']).'\');</script>';

    if (isset($limit_reach)) {
        $msg = str_replace('{max_print}', $max_print, __('Selected items NOT ADDED to print queue. Only {max_print} can be printed at once'));
        utility::jsToastr('Labels Printing', $msg, 'warning');
    } else {
        // update print queue count object
        utility::jsToastr('Labels Printing', __('Selected items added to print queue'), 'success');
    }
    exit();
}

// clean print queue
if (isset($_GET['action']) AND $_GET['action'] == 'clear') {
    utility::jsToastr('Labels Printing', __('Print queue cleared!'), 'success');
    echo '<script type="text/javascript">top.$(\'#queueCount\').html(\'0\');</script>';
    unset($_SESSION['slipbook']);
    exit();
}

// on print action
if (isset($_GET['action']) AND $_GET['action'] == 'print') {
    // check if label session array is available
    if (!isset($_SESSION['slipbook'])) {
        utility::jsToastr('Labels Printing', __('There is no data to print!'), 'error');
        die();
    }

    // biblio data
    $template = file_get_contents(__DIR__ . '/slip.html');

    $table_content = '';
    for ($i=0; $i < 15; $i++) { 
        $table_content .= <<<HTML
        <tr>
            <td style="padding: 10px; border: 1px solid black"></td>
            <td style="padding: 10px; border: 1px solid black"></td>
            <td style="padding: 10px; border: 1px solid black"></td>
            <td style="padding: 10px; border: 1px solid black"></td>
            <td style="padding: 10px; border: 1px solid black"></td>
        </tr>
        HTML;
    }

    ob_start();
    echo '<style>@media print { body {margin: 5mm 5mm 5mm 8mm;} #print {display: none;} @page {margin: 1mm 1mm 1mm 1mm;} * {font-family: Arial} }</style>';
    echo '<a id="print" href="#" onclick="self.print()">Print</a>';
    echo '<section style="display: block; width: 100%">';

    $seq = 0;
    foreach ($_SESSION['slipbook'] as $id) {
        list($biblio_id,$item_code) = explode(':', trim($id));
        
        // Get authors for this biblio
        $author_query = "SELECT ma.author_name 
                        FROM biblio_author as ba
                        LEFT JOIN mst_author as ma ON ma.author_id = ba.author_id
                        WHERE ba.biblio_id = '$biblio_id'";
        $author_result = $dbs->query($author_query);

        $author_string = '';
        if ($author_result) {
            while ($author_row = $author_result->fetch_assoc()) {
                $author_string .= $author_row['author_name'] . ' - ';
            }
        }
        $author_string = trim($author_string, ' - ');

        // Get biblio data
        $biblio_query = "SELECT i.item_code as itemcode, i.call_number as callnumber, b.title
                        FROM item as i
                        LEFT JOIN biblio as b ON b.biblio_id = i.biblio_id
                        WHERE i.item_code = '$item_code'";
        $biblio_result = $dbs->query($biblio_query);

        if (!$biblio_result || $biblio_result->num_rows == 0) {
            continue; // Skip if no data found
        }
        
        $biblio_data = $biblio_result->fetch_assoc();
        $biblio_data['authors'] = empty($author_string) ? '-' : $author_string;
        $biblio_data['libraryname'] = isset($sysconf['library_name']) ? $sysconf['library_name'] : 'Library';
        $biblio_data['position'] = (($seq + 1) % 2) === 0 ? 'left' : 'right';
        $biblio_data['tablecontent'] = $table_content;

        echo str_replace([
            '{itemcode}','{callnumber}','{title}','{authors}','{libraryname}','{position}','{tablecontent}'
        ], $biblio_data, $template);

        $seq++;
    }
    echo '</section>';
    echo '<script>self.print()</script>';
    $content = ob_get_clean();

    
    // unset the session
    // unset($_SESSION['slipbook']);
    // write to file
    $print_file_name = 'slipbook_print_result_'.strtolower(str_replace(' ', '_', $_SESSION['uname'])).'.html';
    $file_write = @file_put_contents(UPLOAD.$print_file_name, $content);
    if ($file_write) {
        echo '<script type="text/javascript">parent.$(\'#queueCount\').html(\'0\');</script>';
        // open result in new window
        echo '<script type="text/javascript">top.$.colorbox({href: "'.SWB.FLS.'/'.$print_file_name.'?v='.date('YmdHis').'", iframe: true, width: 800, height: 500, title: "' . __('Labels Printing') . '"})</script>';
    } else { 
        utility::jsToastr('Labels Printing', str_replace('{directory}', UPLOAD, __('ERROR! Label failed to generate, possibly because {directory} directory is not writable')), 'error'); 
    }
    exit();
}

/* search form */
?>
<div class="menuBox">
<div class="menuBoxInner printIcon">
	<div class="per_title">
    <h2>Cetak Slip Buku</h2>
  </div>
	<div class="sub_section">
    <div class="btn-group">
        <a target="blindSubmit" href="<?php echo $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . '&action=clear'; ?>" class="btn btn-default notAJAX "><?php echo __('Clear Print Queue'); ?></a>
        <a target="blindSubmit" href="<?php echo $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . '&action=print'; ?>" class="btn btn-default notAJAX "><?php echo __('Print Labels for Selected Data'); ?></a>
	</div>
    <form name="search" action="<?php echo $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']; ?>" id="search" method="get" class="form-inline"><?php echo __('Search'); ?>
    <input type="text" name="keywords" class="form-control col-md-3" />
    <input type="submit" id="doSearch" value="<?php echo __('Search'); ?>" class="s-btn btn btn-default" />
    </form>
    </div>
    <div class="infoBox">
        <?php
        echo __('Maximum').' <strong class="text-danger">'.$max_print.'</strong> '.__('records can be printed at once. Currently there is').' ';
        if (isset($_SESSION['slipbook'])) {
            echo '<strong id="queueCount" class="text-danger">'.count($_SESSION['slipbook']).'</strong>';
        } else { echo '<strong id="queueCount" class="text-danger">0</strong>'; }
        echo ' '.__('in queue waiting to be printed.');
        ?>
    </div>
</div>
</div>
<?php
/* search form end */

// create datagrid
$datagrid = new simbio_datagrid();
// table spec
$table_spec = 'item as i inner join biblio as b on b.biblio_id = i.biblio_id';

$datagrid->setSQLColumn(
    'concat(i.biblio_id, \':\', i.item_code)',
    'i.item_code AS \'' . __('Item Code') . '\'',
    'i.call_number AS \'' . __('Call Number') . '\'',
    'b.title AS \'' . __('Title') . '\''
);


$datagrid->setSQLorder('i.last_update DESC');

// is there any search
if (isset($_GET['keywords']) AND $_GET['keywords']) {
    $keywords = utility::filterData('keywords', 'get', true, true, true);
    $datagrid->setSQLcriteria('(i.item_code like \'%' . $keywords . '%\' or b.title like \'%' . $keywords . '%\')');
}

// set table and table header attributes
$datagrid->table_attr = 'id="dataList" class="s-table table"';
$datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
// edit and checkbox property
$datagrid->edit_property = false;
$datagrid->chbox_property = array('itemID', __('Add'));
$datagrid->chbox_action_button = __('Add To Print Queue');
$datagrid->chbox_confirm_msg = __('Add to print queue?');
// set delete proccess URL
$datagrid->chbox_form_URL = $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'];
// put the result into variables
$datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, $can_read);
if (isset($_GET['keywords']) AND $_GET['keywords']) {
    $msg = str_replace('{result->num_rows}', $datagrid->num_rows, __('Found <strong>{result->num_rows}</strong> from your keywords'));
    echo '<div class="infoBox">'.$msg.' : "'.htmlspecialchars($_GET['keywords']).'"<div>'.__('Query took').' <b>'.$datagrid->query_time.'</b> '.__('second(s) to complete').'</div></div>';
}
echo $datagrid_result;
/* main content end */
