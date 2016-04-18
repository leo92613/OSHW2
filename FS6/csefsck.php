<?php
/*************************************INITIATION****************************/
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

/**********************************WRITE BLOCK******************************/
function write_block($write_stream,$filenum) {
	global $block_NAME;
	$file_write = fopen($block_NAME[$filenum],"w");
   	fwrite($file_write,$write_stream);
	fclose($file_write);
	
}

/***************************************************************************/



/*********************************GET THE FILE DIRECTORY********************/
function get_dict($key){
	$tmp=substr($key,strpos($key,"file"));
	//echo $tmp."\n";
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
    $_flag=0;
    $buffer=explode(", ",file_get_contents($block_NAME[0]));
	$buffer[0]=substr($buffer[0],1);
	$buffer[sizeof($buffer)-1]=substr($buffer[sizeof($buffer)-1],0,strlen($buffer[sizeof($buffer)-1])-1);
	//print_r($buffer);
	foreach ($buffer as $key) {
		if (!(strpos($key,"devId")===false)){
			$tmp=explode(":",$key);
			if($tmp[1]!=20)
			{
			fwrite($log,"ERROR:DeviceID is Wrong!\n");
			$_flag=1;
			$tmp[1]=20;
			$key=$tmp[0].":".$tmp[1];//check the Device ID of the Super Block
		}
		}
		if (!(strpos($key,"Time")===false) or !(strpos($key,"time")===false))
		{
			$tmp=explode(":",$key);
			if ($tmp[1]>=time()) {
			$rewirte_flag=1;
			fwrite($log,"ERROR: Block 0"."'s $tmp[0] is WRONG!\n");
			$tmp[1]=time();
			$key=$tmp[0].":".$tmp[1];
			}
		}
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
	if ($_flag==1) {
		$write_stream="{".$buffer[0];
		for ($i=1;$i<sizeof($buffer)-1;$i++)
		 $write_stream=$write_stream.", ".$buffer[$i];
		$write_stream=$write_stream.", ".$buffer[sizeof($buffer)-1]."}";
		write_block($write_stream,0);
	}

/**************************************************************************/


/*********************************INITIATE FREE BLOCK*************************/
    $block_free=array_fill(0,$max_BLOCK,0);
    //print_r($block_free);
    $block_free[0]=1;
	$block_free[$root]=1;
	for ($i=$free_START;$i<=$free_END;$i++){
		$block_free[$i]=1;
	}

/***************************************************************************/
$d_flag=array();
/*******************************CHECK FILE**********************************/
	function file_check($file_num,$file_name){
		global $block_NAME;
		global $log;
		$_flag=0;  //check if the block needs to be rewriten;
		global $block_free;
		$block_free[$file_num]=1;
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
		    if (!(strpos($key,"Time")===false) or !(strpos($key,"time")===false) ){
				$tmp=explode(":",$key);
				if ($tmp[1]>=time()) {
					$_flag=1;
					fwrite($log,"ERROR: File Inode Block $file_num"."'s $tmp[0] is WRONG!\n");
					$tmp[1]=time();
					$key=$tmp[0].":".$tmp[1];
			}
		    }
			if (!(strpos($key,"indirect")===false)){         // the comma is missing between the indirect and location
				$buffer_sub=explode(" ",$key);
				$tmp=explode(":",$buffer_sub[0]);
				$indirect=$tmp[1];
				$tmp=explode(":",$buffer_sub[1]);
				$location=$tmp[1];
				$block_free[$location]=1;
			}					
	    }
	    if ($file_size>1638400)
	    fwrite($log,"ERROR: File $file_name is oversize to store!\n"); //filesize bigger than the size that the file system can handle
		if ($linkcount>1 or $indirect>1)
		fwrite($log,"ERROR: The indirect level of $file_name is oversized!\n");
		if (!(array_key_exists($location,$block_NAME))) 
		fwrite($log,"ERROR: The file $file_name is broken!(Missing Data Block)\n");
		if ($indirect==1 and array_key_exists($location,$block_NAME)) {
			$block_free[$location]=1;
			$tmp=file_get_contents($block_NAME[$location]);
			$tmp=explode(", ",$tmp);
			$flag=0;
			if ($file_size<4096) {
				$flag=1;
				$_flag=1;
				$block_free[$location]=0;
				$location=$tmp[0];
				$block_free[$location]=1;
				$indirect=0;
				$buffer[sizeof($buffer)-1]="indirect:".$indirect." "."location:".$location;
			}
			if ($flag==0) {
			foreach ($tmp as $k) {
				$block_free[$k]=1;
			}
			}
			if ($file_size > sizeof($tmp)*4096) {
			fwrite($log,"ERROR:File $file_name is Broken!(Missing Data Block)\n");//filesize > (length of index block)* block size
			$flag=1;
			}
			if ($flag==0) {
				foreach ($tmp as $value) {
					if (!array_key_exists($value,$block_NAME)) {
					fwrite($log,"ERROR: The file $file_name is broken!(Missing Data Block)\n");// Data block in index stored no data
					}
				}
			}
		}
		if ($_flag==1) {
			//print_r($buffer);
			$write_stream="{".$buffer[0];
			for ($i=1;$i<sizeof($buffer)-1;$i++)
			$write_stream=$write_stream.", ".$buffer[$i];
			$write_stream=$write_stream.", ".$buffer[sizeof($buffer)-1]."}";
			write_block($write_stream,$file_num);
		}
    }


/*******************************CHECK DIRECTORY*****************************/
    function dir_check($dir_num,$parent_dir){
	global $block_NAME;
	global $log;
	$_flag=0;  //check if the block needs to be rewriten;
	global $block_free;
	$block_free[$dir_num]=1;
	$buffer=file_get_contents($block_NAME[$dir_num]);
	$info=get_info($buffer);
	//print_r($info);
	$file_dict=get_dict($buffer);
		$buffer=array();
	foreach ($info as $key ) {
		$buffer[]=$key;
	}
	//print_r($file_dict);
	//$buffer=substr($buffer,1,strlen($buffer)-2);

	for ($i=0;$i<sizeof($buffer);$i++) {
	if (!(strpos($buffer[$i],"Time")===false) or !(strpos($buffer[$i],"time")===false) )
	{
		$tmp=explode(":",$buffer[$i]);
		if ($tmp[1]>=time()) {
		$_flag=1;
		fwrite($log,"ERROR: Block $dir_num"."'s $tmp[0] is WRONG!\n");
		$tmp[1]=time();
		$buffer[$i]=$tmp[0].":".$tmp[1];
		}
	}//check the time
	}
	
	$linkcount=get_linkcount($info);
	if ($linkcount!=sizeof($file_dict)){
	$_flag=1;
	fwrite($log,"ERROR: Linkcount in Block $dir_num is wrong!\n");
	for ($i=0;$i<sizeof($buffer);$i++){
			if (!(strpos($buffer[$i],"linkcount")===false)){
				$tmp=explode(":",$buffer[$i]);
				$tmp[1]=sizeof($file_dict);
				$buffer[$i]=$tmp[0].":".$tmp[1];
	}
	}
	}//check the linkcount
	$dir_list=array();
	$file_list=array();
	if (sizeof($file_dict)<2)
	fwrite($log,"ERROR: filename_to_inode_dict of block $dir_num is invalid!\n");
	else{
	for ($i=0;$i<sizeof($file_dict);$i++) {
		if (!(strpos($file_dict[$i],"d:")===false)){
			$buffer_sub=explode(":",$file_dict[$i]);
			if ($buffer_sub[1] === ".") {
				if($buffer_sub[2] != $dir_num){
					$_flag==1;
			        fwrite($log,"ERROR: The . directory of block $dir_num is wrong!\n");
					$buffer_sub[2]=$dir_num;
					$file_dict[$i] = $buffer_sub[0].":".$buffer_sub[1].":".$buffer_sub[2];
					}
			}
			elseif ($buffer_sub[1] === "..")  {
				if($buffer_sub[2] != $parent_dir){
				$_flag=1;
				fwrite($log,"ERROR: The .. directory of block $dir_num is wrong!\n");
				$buffer_sub[2]=$dir_num;
				$file_dict[$i] = $buffer_sub[0].":".$buffer_sub[1].":".$buffer_sub[2];
				}
				}
            else{
			$dir_list[]=$file_dict[$i];}//get the directory list
		  }
		if (!(strpos($file_dict[$i],"f:")===false)){
			$file_list[]=$file_dict[$i];//get the file list
		}
	}
	}
	//print_r($dir_list);
	//print_r($file_dict);
	//echo "\n";
		if ($_flag==1) {
			$buffer[sizeof($buffer)]="filename_to_inode_dict: {";
			foreach ($file_dict as $key)
				$buffer[sizeof($buffer)-1]=$buffer[sizeof($buffer)-1].$key.", ";
			$buffer[sizeof($buffer)-1]=substr($buffer[sizeof($buffer)-1],0,strlen($buffer[sizeof($buffer)-1])-2);
			$buffer[sizeof($buffer)-1]=$buffer[sizeof($buffer)-1]."}";
			$write_stream="{".$buffer[0];
			for ($i=1;$i<sizeof($buffer)-1;$i++)
			$write_stream=$write_stream.", ".$buffer[$i];
			$write_stream=$write_stream.", ".$buffer[sizeof($buffer)-1]."}";
			//echo $write_stream."\n";
			write_block($write_stream,$dir_num);
		}
	
	foreach ($dir_list as $key) {
		$buffer_sub=explode(":",$key);
		dir_check($buffer_sub[sizeof($buffer_sub)-1],$dir_num);
	}
	//print_r($file_list);
	foreach ($file_list as $value) {
		$buffer_sub=explode(":",$value);
		file_check($buffer_sub[2],$buffer_sub[1]);
	}//check each file in the directory
	
}
/***************************************************************************/

/**********************************CHECK FREE BLOCK LIST********************/
function free_check(){
	global $block_NAME;
	global $block_free;
	global $free_START;
	global $free_END;
	global $log;
	$block_space=array();
	$block_file=array();
	for ($i=$free_START;$i<=$free_END;$i++)
	{
		$buffer=explode(", ",trim(file_get_contents($block_NAME[$i])));
		$block_space[$i]=sizeof($buffer);
	    $flag=0;
		for ($j=0;$j<sizeof($buffer);$j++) {
			if ($block_free[$buffer[$j]]==1){
			$flag=1;
			unset($buffer[$j]);
			fwrite($log,"ERROR: BLOCK $buffer[$j] is not a free block!\n");
			$block_space[$i]--;
			}
			else  {
			$block_file[]=	$buffer[$j];
			}		
		}

		if ($flag=1){
			$write_stream=implode(", ",$buffer);
			write_block($write_stream,$i);
		}
	}
	for ($i=0;$i<sizeof($block_free);$i++) {
		if ($block_free[$i]==0 and !(in_array($i, $block_file))){
			fwrite($log,"ERROR: BLOCK $i is free block but not in the free block list!\n");
			$block_file[]=$i;
			 for ($j=$free_START;$j<=$free_END;$j++) {
				if ($block_space[$j]<400) {
					$block_space[$j]++;
					$buffer=file_get_contents($block_NAME[$j]);
					$write_stream=$buffer.", ".$i;
					echo "djcdijcbd\n".$j;
					write_block($write_stream,$j);
				}
			}
		}		
	}
	//print_r($block_file);
	echo "\n".sizeof($block_file);
}
		
/**********************************MAIN*************************************/
	dir_check($root,$root);
	//print_r($block_free);
	free_check();
	fclose($log);
	
/**********************************END**************************************/
?>