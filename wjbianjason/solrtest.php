<?php
// ini_set('error_log','php-errer.log');
// ini_set('log_errors',true);
ini_set('max_execution_time','500000');
header("Content-Type:text/html;charset=utf8");
error_reporting(E_ALL&~E_NOTICE);
include_once 'SolrUtil.php';
$solrUtil = new SolrUtil('http://www.zhaole365.com:8983/solr/', 'events');
// $datas=array('id'=>864, 'username'=>array('set'=>"asdasdsa"));
// $data=$solrUtil->update($datas);
// $a="小野丽莎";
$data=$solrUtil->select(array('q'=>'*:*','rows'=>15000,'fl'=>'id,description,title'));
// print_r($data);
// exit;
$handle=fopen('test.txt','w');
for($i=0;$i<15000;$i++)
{
	$id=$data['data']['response']["docs"][$i]["id"];
	$description=$data['data']['response']["docs"][$i]["description"];
	$description=str_replace('\n','',$description);
	$title=$data['data']['response']["docs"][$i]["title"];
        $title=str_replace('\n','',$title);
	fwrite($handle,$id."\t".trim($title)."\t".trim($description)."\n");
}	
// $data = $solrUtil->select(array('q'=>"(title:$a AND description:小野)",'fl'=>'id,title','sort'=>"price+asc",'sfield'=>'location'));
// // $data=$solrUtil->add(array('id'=>864, 'username' => '测试'));
// $data = $solrUtil->select(array('q'=>'id:864'));
// print_r($data);
echo "over \n";
fclose($handle);
?>
