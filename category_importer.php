<?php
// env config
ini_set('display_errors', 1);
umask(0);

// mage setup
if (sizeof($argv) != 3) {
	echo "Usage: php ".$argv[0]." <path_to_mage_root> <path_to_tree_file>";
	exit();
}

$mage_root = $argv[1];
$mage_core = $mage_root."/app/Mage.php";
if (!file_exists($mage_core)) {
	echo $mage_core." not found.";
	exit();
}

require_once $mage_core;
Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

$treeFile = $argv[2];
if (!file_exists($treeFile)) {
  die("File not found\n");
}

$handle = @fopen($treeFile, "r");
if (!$handle) {
  die("Error: unexpected fail\n");
}

$last_item_per_offset = array();
while (($buffer = fgets($handle, 4096)) !== false) {
    $file_data            = explode('LIN', $buffer);

    foreach($file_data as $line) {
        $info   = explode('*', $line);
        $info   = array_filter($info);

        $offset = strlen(substr($line, 0, strpos($line,'-')));
        $cat_name = trim(substr($line, $offset+1));

        if (empty($info)) {
          exit;
        }

        $category_collection = Mage::getModel('catalog/category')->getCollection()->addFieldToFilter('name', $cat_name)->setPageSize(1);

      	if (isset($last_item_per_offset[$offset-1]))
      	{
      		$category_collection->addAttributeToFilter('parent_id', (int)$last_item_per_offset[$offset-1]->getId());
      	}

     		if ($category_collection->count()) // item exists, move on to next tree item
      	{
      		$last_item_per_offset[$offset] = $category_collection->getFirstItem();
      		var_dump($last_item_per_offset[$offset]);
      		continue;
      	}

    		if ($offset-1 == 0 && !isset($last_item_per_offset[$offset-1])) // no root item found
    		{
    			echo "ERROR: root category not found. Please create the root\n";
    			exit;
    		}

    		if(!isset($last_item_per_offset[$offset-1])) // no parent found. something must be wrong in the file
    		{
    			echo "ERROR: parent item does not exist. Please check your tree file\n";
    			exit;
    		}

        $parentitem = $last_item_per_offset[$offset-1];

        // create a new category item
        $category = Mage::getModel('catalog/category');
        $category->setStoreId(0);

        $category->addData(array(
        	'name' 			    => $cat_name,
        	'meta_title'	  => $cat_name,
        	'display_mode'	=> Mage_Catalog_Model_Category::DM_PRODUCT,
        	'is_active'		  => 1,
        	'is_anchor'		  => 1,
        	'path'			    => $parentitem->getPath(),
        ));

        try {
        	$category->save();
        } catch (Exception $e){
        	echo "ERROR: {$e->getMessage()}\n";
        	die();
        }

        $last_item_per_offset[$offset] = $category;
        echo "> Created category '{$cat_name}'\n";

    }
}

if (!feof($handle)) {
    echo "Error: unexpected fgets() fail\n";
}

fclose($handle);
