<?php
/*************************************INITIATION*****************************/
	$current_PATH= getcwd();
	$all_FILE=scandir($current_PATH,1);
    foreach ($all_FILE as $key) {
	if (!(strpos($key,"fusedata")===false)){
		$buffer_sub=explode(".",$key);
		$block_NAME[$buffer_sub[1]]="fusedata.".$buffer_sub[1];
	  }
    }
	$log = fopen("log.txt","w");
    //print_r($block_NAME);
/***************************************************************************/

/**********************************CHECK BLOCK TIME*************************/
function checktime($num,$key,$log){
	if ($key[1] >= time()){
		fwrite($log,"ERROR: Block $num"."'s $key[0] is WRONG!\n");
	}
}
/***************************************************************************/

/**********************************CHECK FILE TIME**************************/
function checktime_file($file_name,$key,$log){
	if ($key[1] >= time()){
		fwrite($log,"ERROR: The file $file_name"."'s $key[0] is WRONG!\n");
	}
}
/***************************************************************************/

/*********************************GET THE FILE DIRECTORY********************/
function get_dict($key){
	$tmp=substr($key,strpos($key,"file"));
	$tmp=substr($tmp,0,strlen($tmp)-1);
	$tmp=substr($tmp,strpos($tmp,"{"));
	$tmp=trim($tmp,"{}");
	$tmp=explode(", ",$tmp);
    return $tmp;	
}

/***************************************************************************/

/***********************************GET THE DIRECTORY INFO******************/
function get_info($key){
	$tmp=substr($key,0,strpos($key,"file")-2);
	$tmp=trim($tmp,"{");
	$tmp=explode(", ",$tmp);
	return $tmp;
}
/***************************************************************************/

/**********************************GET LINKCOUNT****************************/
function get_linkcount($key){
	foreach ($key as $value) {
		if (!(strpos($value,"linkcount")===false)){
			$tmp=explode(":",$value);
			return $tmp[1];
		}
	}
}
/***************************************************************************/

/**********************************CHECK SUPER BLOCK************************/

    $buffer=explode(", ",file_get_contents($block_NAME[0]));
	$buffer[0]=substr($buffer[0],1);
	$buffer[sizeof($buffer)-1]=substr($buffer[sizeof($buffer)-1],0,strlen($buffer[sizeof($buffer)-1])-1);
	//print_r($buffer);
	foreach ($buffer as $key) {
		if (!(strpos($key,"devId")===false)){
			$buffer_sub=explode(":",$key);
			if($buffer_sub[1]!=20)
			fwrite($log,"ERROR:DeviceID is Wrong!\n");//check the Device ID of the Super Block
		}
		if (!(strpos($key,"Time")===false) or !(strpos($key,"time")===false) )
		checktime(0,explode(":",$key),$log);
		if (!(strpos($key,"freeStart")===false)){
			$buffer_sub=explode(":",$key);
			$free_START=$buffer_sub[1];// obtain the free block list start block
		}
		if (!(strpos($key,"freeEnd")===false)){
			$buffer_sub=explode(":",$key);
			$free_END=$buffer_sub[1];// obtain the free block list end block
		}
		if (!(strpos($key,"root")===false)) {
			$buffer_sub=explode(":",$key);
			$root=trim($buffer_sub[1]);// obtain the root directory
		}
		if (!(strpos($key,"maxBlocks")===false)) {
			$buffer_sub=explode(":",$key);
			$max_BLOCK=$buffer_sub[1];// obtain the max blocks
		}
	}

/**************************************************************************/

/*********************************CHECK FREE BLOCK*************************/
    $list_FREEBLOCK= array();
	for ($key=$free_START;$key<=$free_END;$key++){
		$buffer=explode(", ",file_get_contents($block_NAME[$key]));
		if ($buffer<1){
			fwrite($log,"ERROR:The free block list $key is NULL! \n");
		}
		foreach ($buffer as $value) {
			if (array_key_exists($value,$block_NAME)) 
			if (file_get_contents($block_NAME[$value])!= null)
			fwrite($log,"ERROR: Block $value is not free but in the free block list! ");
		}
		$list_FREEBLOCK=array_merge($list_FREEBLOCK,$buffer);
	}
	//print_r($list_FREEBLOCK);
	
	for ($key=0;$key<$max_BLOCK;$key++) {
		if ((!array_key_exists($key,$block_NAME)) and (!in_array($key,$list_FREEBLOCK)))
		fwrite($log,"ERROR: The free block $key is not in the free block list!\n");
		if ((array_key_exists($value,$block_NAME)) and (!in_array($key,$list_FREEBLOCK)))
		if (file_get_contents($block_NAME[$key]) == null)
		fwrite($log,"ERROR: The free block $key is not in the free block list!\n");	}
/***************************************************************************/

/*******************************CHECK DIRECTORY*****************************/
    function dir_check($dir_num,$block_NAME,$log,$parent_dir){
	$buffer=file_get_contents($block_NAME[$dir_num]);
	$info=get_info($buffer);
	//print_r($info);
	$file_dict=get_dict($buffer);
	//print_r($file_dict);
	foreach ($info as $key) {
	if (!(strpos($key,"Time")===false) or !(strpos($key,"time")===false) )
	checktime($dir_num,explode(":",$key),$log);
	}//check the time
	$linkcount=get_linkcount($info);
	if ($linkcount!=sizeof($file_dict))
	fwrite($log,"ERROR: Linkcount in Block $dir_num is wrong!\n");//check the linkcount
	$dir_list=array();
	$file_list=array();
	if (sizeof($file_dict)<2)
	fwrite($log,"ERROR: filename_to_inode_dict of block $dir_num is invalid!");
	else{
	foreach ($file_dict as $key) {
		if (!(strpos($key,"d:")===false)){
			$buffer=explode(":",$key);
			if ($buffer[1] === ".") {
				if($buffer[2] != $dir_num)
			fwrite($log,"ERROR: The . directory of block $dir_num is wrong!\n");}
			elseif ($buffer[1] === "..")  {
				if($buffer[2] != $parent_dir)
				fwrite($log,"ERROR: The .. directory of block $dir_num is wrong!\n");}
            else{
			$dir_list[]=$key;}//get the directory list
		  }
		if (!(strpos($key,"f:")===false)){
			$file_list[]=$key;//get the file list
		}
	}
	}
	//print_r($dir_list);
	foreach ($dir_list as $key) {
		$buffer=explode(":",$key);
		//echo $buffer[2]."\n";
		dir_check($buffer[sizeof($buffer)-1],$block_NAME,$log,$dir_num);
	}
	//print_r($file_list);
	foreach ($file_list as $value) {
		$buffer_sub=explode(":",$value);
		if (sizeof($buffer_sub)>3) {
			$filename=$buffer_sub[1];
			for ($i=2;$i<sizeof($buffer_sub)-1;$i++) {
			$filename=$filename.$buffer_sub[i];
			}
			file_check($buffer_sub[sizeof($buffer_sub)-1],$block_NAME,$log,$filename);
		}
		else
		file_check($buffer_sub[2],$block_NAME,$log,$buffer_sub[1]);
	}
}
/***************************************************************************/

/*******************************CHECK FILE**********************************/
	function file_check($file_num,$block_NAME,$log,$filename){
		$buffer=file_get_contents($block_NAME[$file_num]);
		$buffer=trim($buffer,"{}");
        $buffer=explode(", ",$buffer);
		//print_r($buffer);
		foreach ($buffer as $key) {
			if (!(strpos($key,"size:")===false)){
			$tmp=explode(":",$key);
			$file_size=$tmp[1];
		    }
			if  (!(strpos($key,"linkcount")===false)){
				$tmp=explode(":",$key);
				$linkcount=$tmp[1];
		    }
		    if (!(strpos($key,"Time")===false) or !(strpos($key,"time")===false) )
			checktime_file($filename,explode(":",$key),$log);//check time
			if (!(strpos($key,"indirect")===false)){// the comma is missing between the indirect and location
				$buffer_sub=explode(" ",$key);
				$tmp=explode(":",$buffer_sub[0]);
				$indirect=$tmp[1];
				$tmp=explode(":",$buffer_sub[1]);
				$location=$tmp[1];
			}					
	    }
	    if ($file_size>1638400)
	    fwrite($log,"ERROR: File $file_name is oversize to store!\n");
		if ($linkcount!=$indirect)
			fwrite($log,"ERROR: File $file_name"."'s linkcount mismatches the indirect!\n");
		if ($linkcount>1 or $indirect>1)
		fwrite($log,"ERROR: The indirect level of $file_name is oversized!\n");
		if (!(array_key_exists($location,$block_NAME))) 
		fwrite($log,"ERROR: The file $file_name is broken!(Missing Data Block)\n");
		if ($linkcount==$indirect and $indirect==1 and array_key_exists($location,$block_NAME)) {
			$tmp=file_get_contents($block_NAME[$location]);
			$tmp=explode(", ",$tmp);
			$flag=0;
			if ($file_size > sizeof($tmp)*4096) {
			fwrite($log,"ERROR:File $file_name is oversize to store!\n");
			$flag=1;
			}
			if ($flag==0) {
				foreach ($tmp as $value) {
					if (!array_key_exists($value,$block_NAME)) {
					fwrite($log,"ERROR: The file $file_name is broken!(Missing Data Block)\n");
					}
				}
			}
		}
  }		
/**********************************MAIN*************************************/
	dir_check($root,$block_NAME,$log,$root);
	fclose($log);
?>