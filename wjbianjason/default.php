<?php
/**   
* 找乐365微信公共平台代码   
*   
* @version     1..........
* @update      20150423   1.Add the img similar compare 2.Add the Html filter
* @author      wjbianjason   
*
*/

/**
 * 预处理
 */
require_once('SolrUtil.php');//solr php查询库
require_once('database.php');//数据库存储类
require_once('ImgCompare.php');//判定图片相似度类

ini_set('max_execution_time','50000');
ini_set('error_log','/var/www/php-errer.log');
ini_set('log_errors',true);

header("Content-Type:text/html;charset=utf8");
error_reporting(E_ALL&~E_NOTICE);

define('URL_PATH',"123.56.93.141/log.php");//Post 传参



/**
 * 调用
 */
$wechatObj = new wechat();
$wechatObj->responseMsg();

/**
 * [微信公共平台]
 * @var wechat
 */
class wechat {

    public $solrEvent;
    public $logTime;
    public $solrUser;
    public $solrGroup;
    public $userid;
    public $latitude;
    public $longitude;
    public $page = 0;
    public $flag;
    public $default = "该按键正在建设中...";
    public $welcome = "欢迎使用找乐365！我们将为您提供各类线下活动信息。您可以通过菜单进行检索，也可以直接输入您想检索的内容，精彩将会立即呈现在你的面前！";
    public $textTpl = "<xml>
        <ToUserName><![CDATA[%s]]></ToUserName>
        <FromUserName><![CDATA[%s]]></FromUserName>
        <CreateTime>%s</CreateTime>
        <MsgType><![CDATA[text]]></MsgType>
        <Content><![CDATA[%s]]></Content>
        <FuncFlag>0</FuncFlag>
        </xml>";
    public $textImg = "<xml>
        <ToUserName><![CDATA[%s]]></ToUserName>
        <FromUserName><![CDATA[%s]]></FromUserName>
        <CreateTime>%s</CreateTime>
        <MsgType><![CDATA[news]]></MsgType>
        <ArticleCount>%s</ArticleCount>
        <Articles>%s</Articles>
        </xml>";
    public $textItem = "<item>
        <Title><![CDATA[%s]]></Title> 
        <Description><![CDATA[]]></Description>
        <PicUrl><![CDATA[%s]]></PicUrl>
        <Url><![CDATA[%s]]></Url>
        </item>";
    /**
     * [构造函数，初始化solr查询句柄]
     */
    function __construct(){
        $this->solrEvent = new SolrUtil('http://www.zhaole365.com:8983/solr/', 'events');
        $this->solrUser = new SolrUtil('http://www.zhaole365.com:8983/solr/', 'wechat');
        $this->solrGroup= new SolrUtil('http://www.zhaole365.com:8983/solr/', 'groups');
    }
    /**
     * [过滤掉标题中的特殊符号]
     * @param  [type] &$title [description]
     * @return [type]         [description]
     */
    public function filterHtml(&$title)
    {
        $rtn = preg_replace("/&[a-zA-Z]*;/","",$title);
        $title = $rtn;
    }
    /**
     * [根据图片汉明距离判定相似度然后去重]
     * @param  [type] $imgurl [description]
     * @param  [type] $imgarr [description]
     * @return [type]         [description]
     */
    public function filterImg($imgurl,$imgarr)
    {
        $ori = ImageHash::hashImageFile($imgurl);
        $IsSim = 0;
        foreach($imgarr as $desImg){
            $des = ImageHash::hashImageFile($desImg);
            if(ImageHash::isHashSimilar($ori, $des)){
                $IsSim = 1;
            }
        }
        return $IsSim;
    }
    /**
     * [getBird solr检索]
     * @param  [type] $content [检索内容]
     * @param  [type] $city    [检索城市]
     * @param  [type] $sort    [检索类型]
     * @return [array]$result  [返回标题、图片、活动id]
     */
    public function getBird($content,$city)
    {

        $params = array();
        $params['q'] = "(title:$content OR description:$content)";        
        $params['start'] = $this->page;
        $params['rows'] = 20;
        $date = date('Y-m-d,H:i:s',time()-8*60*60);
        $time = explode(',',$date);
        $shijian = $time[0]."T".$time[1]."Z";
        $params['fq'] = "happentime:[$shijian TO *]";
        $params['fl'] = "id,title,imageurl,eventdate,location";

        if($city != "")
        { 
         $params['q'] = "(title:$content OR description:$content)AND location_description:$city";
        }

        $bird_result = $this->solrEvent->select($params);

        $rtn = array();
        $i = 0;
        $j = 0;
        $rtn['temp'] = array();
        $rtn['img'] = array();

        if($bird_result['data']['response']['numFound'] == 0)
        {
            $rtn['error'] = "很抱歉，暂时没有您想关注的活动，我们将尽快添加！谢谢支持";
        }
        else {
                $displayNum = 4;
                if($bird_result['data']['response']['numFound'] < 4)
                     $displayNum = $bird_result['data']['response']['numFound'];
                for($i = 0;($i<20&&$j<$displayNum);$i++)
                {
                    self::filterHtml($bird_result['data']['response']["docs"][$i]["title"]);
                    
                    if(trim($bird_result['data']['response']["docs"][$i]["title"]) == "")
                    {
                        continue;
                    }

                    if(in_array(trim($bird_result['data']['response']["docs"][$i]["title"]),$rtn['temp']))
                    {
                        if($displayNum<4)
                        {
                            $j++;
                        }
                        continue;
                    }

                    if(self::filterImg($bird_result['data']['response']["docs"][$i]["imageurl"],$rtn['img']) == 1){
                        continue;
                    }
                    else{
                        $rtn['img'][] = $bird_result['data']['response']["docs"][$i]["imageurl"];
                    }

                    $j++;

                    $rtn['temp'][] = trim($bird_result['data']['response']["docs"][$i]["title"]);
                    $location = explode(',',$bird_result['data']['response']["docs"][$i]["location"]);

                    if(!empty($this->latitude))
                    {
                        $getLocation = "&origins=".(string)$this->latitude.",".(string)$this->longitude;
                        $aimLocation = "&destinations=".(string)$location[0].",".(string)$location[1];
                        $apiUrl = "http://api.map.baidu.com/direction/v1/routematrix?ak=DDa0545d1e03780687ead34bc4701ee1&output=xml&tatics=11";
                        $resUrl = $apiUrl.$getLocation.$aimLocation;
                        $rtn1 = file_get_contents($resUrl);
                        $rtnCnt = simplexml_load_string($rtn1,'SimpleXMLElement',LIBXML_NOCDATA);
                        $rtnDistance = "    ".(string)$rtnCnt->result->elements->distance->text;
                    }

                    $rtn['title'][] = trim($bird_result['data']['response']["docs"][$i]["title"])."\n".$bird_result['data']['response']["docs"][$i]["eventdate"].((isset($rtnDistance))?$rtnDistance:"");
                    $rtn['imageurl'][] = $bird_result['data']['response']["docs"][$i]["imageurl"];
                    $rtn['eventid'][] = $bird_result['data']['response']["docs"][$i]["id"];
                }
                    $this->solrUser->update(array('id'=>$this->userid,'page'=>array('set'=>($this->page+$i-$j)))); 
            }
            $this->logMy();//log 用户行为
            if(empty($rtn['title'])){
                $rtn = array();
                $rtn['error'] = "很抱歉，暂时没有您想关注的活动，我们将尽快添加！谢谢支持";
            }
            return $rtn;
    }
    /**
     * [getUser 判断是否为新用户，并将用户id存入公有变量]
     * @return [type] [无]
     */
    public function getUser()
    {
        $params = array();
        $params['q'] = "id:$this->userid";

        $user_result = $this->solrUser->select($params);

        if($user_result['data']['response']['numFound'] == 0)
        {
            $this->solrUser->add(array('id'=>$this->userid,'page'=>0));
            $this->page=0;
            $this->flag=1;
        }
        else
        {
            if(!isset($user_result['data']['response']['docs'][0]['fakeid']))
            {
                $this->flag = 1;
            }

            if(isset($user_result['data']['response']['docs'][0]['latitude']))
            {
                $this->latitude = $user_result['data']['response']['docs'][0]['latitude'];
                $this->longitude = $user_result['data']['response']['docs'][0]['longitude'];
            }
        }
    }
    /**
     * [getGroup 获取乐群]
     * @param  [type] $flag [description]
     * @return [type]       [description]
     */
    public function getGroup($flag)
    {
        if($flag == '1')
        {
            $datas = $this->solrGroup->select(array('q'=>'*:*','fl'=>'title,member_userid,imageurl,id','rows'=>'40'));
            $sort = array();
            foreach($datas['data']['response']['docs'] as $data)
            {
                $sort[] = count($data['member_userid']);
            }
            arsort($sort);
            $i = 0;
            $num = array();
            foreach($sort as $key =>$v)
            {
                $num[] = $key;
                $i++;
                if($i == 4){
                    break;
                }
            }
        }
        else if($flag == '2')
        {
            $datas = $this->solrGroup->select(array('q'=>'*:*','sort'=>'ts_created+desc','fl'=>'title,imageurl,id'));
            $num = array(0,1,2,3);
        }
        for($j = 0;$j<4;$j++)
        {
            $rtn['title'][] = $datas['data']['response']["docs"][$num[$j]]["title"];
            $rtn['imageurl'][] = $datas['data']['response']["docs"][$num[$j]]["imageurl"];
            $rtn['groupid'][] = $datas['data']['response']["docs"][$num[$j]]["id"];
        }
        return $rtn;
    }
    public function setlat($latitude,$longitude)
    {
        $lat = $latitude;
        $lng = $longitude;
        $this->latitude = $latitude;
        $this->longitude = $longitude;

        $url1 = "http://api.map.baidu.com/geocoder/v2/?ak=DDa0545d1e03780687ead34bc4701ee1&callback=renderReverse&location=".$lat.",".$lng."&output=xml&pois=1";
        $rtn = file_get_contents($url1);
        $rtnCnt = simplexml_load_string($rtn,'SimpleXMLElement',LIBXML_NOCDATA);
        $usercity = trim($rtnCnt->result->addressComponent->city);
        $area = trim($rtnCnt->result->addressComponent->district);
        $usercity = str_replace("市", "",(string)$usercity);

        $params = array('id'=>$this->userid,'address'=>array('set'=>(string)$area),'latitude'=>array('set'=>(string)$lat),'longitude'=>array('set'=>(string)$lng),'usercity'=>array('set'=>(string)$usercity));
        
        $this->solrUser->update($params);
        
        $this->saveMy($area);
    }
    /**
     * [setAddress 存入用户的位置信息，并调用百度API获取用户的经纬度]
     * @param [type] $address   [所在位置的label]
     * @param [type] $latitude  [纬度]
     * @param [type] $longitude [经度]
     */
    public function setAddress($address,$latitude,$longitude)
    {
        $city = "北京市";
        $map_api_url = 'http://api.map.baidu.com/geocoder/v2/';
        $url = $map_api_url."?ak=HL2OtpqEFglWT1j2RoS62eRD&address=".$address."&city=".$city."&output=xml";
        $rt = file_get_contents($url);
        $rtCnt = simplexml_load_string($rt,'SimpleXMLElement',LIBXML_NOCDATA);

        $lat = $rtCnt->result->location->lat;
        $lng = $rtCnt->result->location->lng;
        $this->latitude = $lat;
        $this->longitude = $lng;

        $url1 = "http://api.map.baidu.com/geocoder/v2/?ak=DDa0545d1e03780687ead34bc4701ee1&callback=renderReverse&location=".$lat.",".$lng."&output=xml&pois=1";
        $rtn = file_get_contents($url1);
        $rtnCnt = simplexml_load_string($rtn,'SimpleXMLElement',LIBXML_NOCDATA);
        $usercity = trim($rtnCnt->result->addressComponent->city);
        $area = trim($rtnCnt->result->addressComponent->district);
        $usercity = str_replace("市", "",(string)$usercity);
        
        $params = array('id'=>$this->userid,'address'=>array('set'=>(string)$address),'latitude'=>array('set'=>(string)$lat),'longitude'=>array('set'=>(string)$lng),'usercity'=>array('set'=>(string)$usercity));
        
        $this->solrUser->update($params);
        $this->saveMy($area);
    }
    public function saveMy($area){
        $params['q'] = "id:$this->userid";
        $result = $this->solrUser->select($params);

        $mysql = new SaeMysql();
        $sql = "INSERT  INTO `wechat` (`userid`, `city`, `area`,`latitude`,`longitude`,`address`,`title`) VALUES ('".$this->userid."','".$result['data']['response']["docs"][0]['usercity']."','".$area."','".$result['data']['response']["docs"][0]['latitude']."','".$result['data']['response']["docs"][0]['longitude']."','".$result['data']['response']["docs"][0]['address']."','".$result['data']['response']["docs"][0]['title']."')";
        $mysql->runSql($sql);
        
        $mysql->closeDb();
    }
    public function logMy(){
        $params['q'] = "id:$this->userid";
        $result = $this->solrUser->select($params);

        $mysql = new SaeMysql();
        $sql = "INSERT  INTO `user_log` (`id`, `operation`, `title`,`createtime`) VALUES ('".$this->userid."','".$result['data']['response']["docs"][0]['operation']."','".$result['data']['response']["docs"][0]['title']."','".time()."')";
        $mysql->runSql($sql);
        
        $mysql->closeDb();
    }
    /**
     * [getSave 返回用户的收藏]
     * @return [type] [返回标题、图片、活动id]
     */
    public function getSave(){
        $params['q'] = "id:$this->userid";
        $params['fl'] = "save";

        $save_result = $this->solrUser->select($params);
        $save_id = $save_result['data']['response']['docs'][0]['save'];

        if(empty($save_id))
        {
            $rtn['error'] = "您目前还没有收藏活动或收藏的活动已结束";
        }
        else{
            $eventid = explode('|',$save_id);
            $params = array();
            $params['fl'] = "id,title,imageurl,location,eventdate";

            for($i = 0;$i<(count($eventid)-1);$i++)
            {
                $params['q'] = "id:".(string)$eventid[$i];
                $save_bird = $this->solrEvent->select($params);

                if($save_bird['data']['response']['numFound']==0)
                {
                    continue;
                }

                $location = explode(',',$save_bird['data']['response']["docs"][0]["location"]);
                
                if(!empty($this->latitude))
                {
                    $getLocation = "&origins=".(string)$this->latitude.",".(string)$this->longitude;
                    $aimLocation = "&destinations=".(string)$location[0].",".(string)$location[1];
                    $apiUrl = "http://api.map.baidu.com/direction/v1/routematrix?ak=DDa0545d1e03780687ead34bc4701ee1&output=xml&tatics=11";
                    $resUrl = $apiUrl.$getLocation.$aimLocation;
                    $rtn1 = file_get_contents($resUrl);
                    $rtnCnt = simplexml_load_string($rtn1,'SimpleXMLElement',LIBXML_NOCDATA);
                    $rtnDistance = "    ".(string)$rtnCnt->result->elements->distance->text;
                }

                if($save_bird['data']['response']["docs"][0]["eventdate"]<date('Y.m.d'))
                {
                    continue;
                }

                $rtn['title'][] = $save_bird['data']['response']["docs"][0]["title"]."\n".$save_bird['data']['response']["docs"][0]["eventdate"].((isset($rtnDistance))?$rtnDistance:"");
                $rtn['imageurl'][] = $save_bird['data']['response']["docs"][0]["imageurl"];
                $rtn['eventid'][] = $save_bird['data']['response']["docs"][0]["id"];
                
                if(count($rtn['title'])==10)
                {
                    break;
                }
            }
        }
        return $rtn;
    }
    /**
     * [saveGroup 显示收藏的组]
     * @return [type] [description]
     */
    public function saveGroup()
    {
        $params['q'] = "id:$this->userid";
        $params['fl'] = "savegroup";

        $save_result = $this->solrUser->select($params);
        $group_id = $save_result['data']['response']['docs'][0]['savegroup'];

        if(empty($group_id))
        {
            $rtn['error'] = "您目前还没有收藏乐群";
        }
        else{
            $params = array();
            $params['fl'] = "id,title,imageurl";
            $save_id = explode('|',$group_id);

            for($i=0;$i<(count($save_id)-1);$i++)
            {
                $params['q'] = "id:".(string)$save_id[$i];
                $save_bird = $this->solrGroup->select($params);
                $rtn['title'][] = $save_bird['data']['response']["docs"][0]["title"];
                $rtn['imageurl'][] = $save_bird['data']['response']["docs"][0]["imageurl"];
                $rtn['groupid'][] = $save_bird['data']['response']["docs"][0]["id"];
            }
        }
        return $rtn;
    }
    /**
     * [nextPage 返回下一页的结果]
     * @param  [type] $fromUsername [来源用户的id]
     * @param  [type] $toUsername   [目标用户的id]
     * @param  [type] $time         [时间戳]
     * @return [type]               [返回标题、图片、活动id]
     */
    public function nextPage($fromUsername,$toUsername,$time){
        $page_result = $this->solrUser->select(array('q'=>"id:$this->userid"));

        $operation_get = $page_result['data']['response']['docs'][0]['operation'];
        $operation_array = explode('-',$operation_get);
        $operation = $operation_array[0];

        $this->page = $page_result['data']['response']['docs'][0]['page']+4;
        $this->solrUser->update(array('id'=>$this->userid,'page'=>array('set'=>$this->page)));

        if($operation=="search")
        {
            $keyContent=$page_result['data']['response']['docs'][0]['title'];
            $keyloc=$page_result['data']['response']['docs'][0]['usercity'];
            $result=self::getBird($keyContent,$keyloc);
        }
        else if($operation=='today')
        {
            $result=self::getToday();
        }
        else if($operation=='week')
        {
            $result=self::getWeek();
        }
        else if($operation=='around')
        {
            $result=self::getAround();
        }

        if(isset($result['error']))
        {
            $resultStr=sprintf($this->textTpl,$fromUsername,$toUsername,$time,$result['error']);
        }
        else { 
            $num=count($result['title']);
            $resultItem="";
            for($i=0;$i<$num;$i++)
            {  
           
                $url="http://".URL_PATH."?eventid=".$result['eventid'][$i]."&userid=".$this->userid.(empty($this->latitude)?"":("&lng=".$this->longitude."&lat=".$this->latitude));
                $resultItem .=sprintf($this->textItem,$result['title'][$i],$result['imageurl'][$i],$url);
            }
            $resultStr = sprintf($this->textImg,$fromUsername,$toUsername,$time,$num,$resultItem);
            }
        return $resultStr;
    }
    public function getContact($friend,$message,$createTime){
        $errno = 0; 
        $errstr = "";
        $timeout = 5; 
        $fp = fsockopen('localhost',80,$errno,$errstr,$timeout);

        if(empty($friend))
            fputs($fp,"GET /weixindenglu.php?flag=0&message=$message&id=$this->userid&createTime=$createTime\r\n");
        else
            fputs($fp,"GET /weixindenglu.php?flag=0&message=$message&friend=$friend&id=$this->userid&createTime=$createTime\r\n");
        
        fclose($fp);
    }
    public function setfake($createTime)
    {   
        $errno = 0; 
        $errstr = "";
        $timeout = 5; 
        $fp = fsockopen('localhost',80,$errno,$errstr,$timeout);

        fputs($fp,"GET /weixindenglu.php?id=$this->userid&createTime=$createTime&flag=1\r\n");
        
        fclose($fp);
    }
    /**
     * [getToday 获取今天的活动，默认选择用户所在的城市]
     * @return [type] [返回标题、图片、活动id]
     */
    public function getToday(){
        $params['q'] = "*:*";

        $today_result = $this->solrUser->select(array('q'=>"id:$this->userid"));
        $content = $today_result['data']['response']['docs'][0]['title'];
        $usercity = $today_result['data']['response']['docs'][0]['usercity'];

        if(!empty($content))
        {
            $params['q'] = '( '.$params['q']." OR title:$content OR description:$content )";
        }

        if($usercity!="")
        {
            $params['q'] = $params['q']." AND location_description:$usercity";
        }

        $params['start'] = $this->page;
        $params['rows'] = 4;
        $date = date('Y-m-d,H:i:s',time()-8*60*60);
        $time = explode(',',$date);
        $shijian = $time[0]."T".$time[1]."Z";
        $time2 = date('Y-m-d')."T15:59:59Z";
        $params['fq'] = "happentime:[$shijian TO $time2]";
        
        $resultToday = $this->solrEvent->select($params);
        
        if($resultToday['data']['response']['numFound']<=$this->page)
        {
            $answer['error'] = "今天您所在城市已没有未开始的活动";
        }
        else{
            for($i=0;$i<4;$i++)
            {
                $location = explode(',',$resultToday['data']['response']["docs"][$i]["location"]);
                if(!empty($this->latitude))
                {
                    $getLocation = "&origins=".(string)$this->latitude.",".(string)$this->longitude;
                    $aimLocation = "&destinations=".(string)$location[0].",".(string)$location[1];
                    $apiUrl = "http://api.map.baidu.com/direction/v1/routematrix?ak=DDa0545d1e03780687ead34bc4701ee1&output=xml&tatics=11";
                    $resUrl = $apiUrl.$getLocation.$aimLocation;

                    $rtn1 = file_get_contents($resUrl);
                    $rtnCnt = simplexml_load_string($rtn1,'SimpleXMLElement',LIBXML_NOCDATA);
                    $rtnDistance = "    ".(string)$rtnCnt->result->elements->distance->text;
                }

                if(empty($resultToday['data']['response']["docs"][$i]["title"]))
                {
                    continue;
                }

                $answer['title'][] = $resultToday['data']['response']["docs"][$i]["title"]."\n".$resultToday['data']['response']["docs"][$i]["eventdate"].((isset($rtnDistance))?$rtnDistance:"");
                $answer['imageurl'][] = $resultToday['data']['response']['docs'][$i]['imageurl'];
                $answer['eventid'][] = $resultToday['data']['response']['docs'][$i]['id'];
            }
        }
        $this->logMy();
        return $answer;
    }
    /**
     * [getWeek 获取最近的活动，默认选择用户所在的城市]
     * @return [type] [返回标题、图片、活动id]
     */
    public function getWeek(){
        $params['q'] = "*:*";

        $today_result = $this->solrUser->select(array('q'=>"id:$this->userid"));
        $content = $today_result['data']['response']['docs'][0]['title'];
        $usercity = $today_result['data']['response']['docs'][0]['usercity'];

        if($usercity!="")
        {
            $params['q'] = $params['q']." AND location_description:$usercity";
        }
        $params['start'] = $this->page;
        $params['rows'] = 20;
        $params['sort'] = "happentime+asc";
        $date = date('Y-m-d,H:i:s',time()-8*60*60);
        $time = explode(',',$date);
        $shijian = $time[0]."T".$time[1]."Z";
        $time2 = date('Y-m-d',time()+7*24*60*60)."T15:59:59Z";
        $params['fq'] = "happentime:[$shijian TO $time2]";

        $resultToday = $this->solrEvent->select($params);
        
        $i = 0;
        $j = 0;
        $answer['temp'] = array();
        $answer['img'] = array();
        if($resultToday['data']['response']['numFound']==0)
        {
            $answer['error']="这周您所在城市没有任何活动";
        }
        else{
            for($i=0;($i<20&&$j<4);$i++)
            {
                self::filterHtml($resultToday['data']['response']["docs"][$i]["title"]);
                if(in_array(trim($resultToday['data']['response']["docs"][$i]["title"]),$answer['temp']))
                {
                    continue;
                }

                if(self::filterImg($resultToday['data']['response']["docs"][$i]["imageurl"],$answer['img']) == 1){
                        continue;
                }
                else{
                        $answer['img'][] = $resultToday['data']['response']["docs"][$i]["imageurl"];
                }

                $j++;
                $answer['temp'][]=trim($resultToday['data']['response']["docs"][$i]["title"]);

                $location=explode(',',$resultToday['data']['response']["docs"][$i]["location"]);
                
                if(!empty($this->latitude))
                {
                    $getLocation = "&origins=".(string)$this->latitude.",".(string)$this->longitude;
                    $aimLocation = "&destinations=".(string)$location[0].",".(string)$location[1];
                    $apiUrl = "http://api.map.baidu.com/direction/v1/routematrix?ak=DDa0545d1e03780687ead34bc4701ee1&output=xml&tatics=11";
                    $resUrl = $apiUrl.$getLocation.$aimLocation;
                    
                    $rtn1 = file_get_contents($resUrl);
                    $rtnCnt = simplexml_load_string($rtn1,'SimpleXMLElement',LIBXML_NOCDATA);
                    $rtnDistance = "    ".(string)$rtnCnt->result->elements->distance->text;
                }

                $answer['title'][] = trim($resultToday['data']['response']["docs"][$i]["title"])."\n".$resultToday['data']['response']["docs"][$i]["eventdate"].((isset($rtnDistance))?$rtnDistance:"");
                $answer['imageurl'][] = $resultToday['data']['response']['docs'][$i]['imageurl'];
                $answer['eventid'][] = $resultToday['data']['response']['docs'][$i]['id'];
            }
            $this->solrUser->update(array('id'=>$this->userid,'page'=>array('set'=>($this->page+$i-4)))); 
        }
        $this->logMy();
        return $answer;
    }
    /**
     * [getdistance 通过经纬度计算距离]
     * @param  [type] $lng1 [经度1]
     * @param  [type] $lat1 [纬度1]
     * @param  [type] $lng2 [经度2]
     * @param  [type] $lat2 [纬度2]
     * @return [type]       [距离（米）]
     */
    /* public function getdistance($lng1,$lat1,$lng2,$lat2){
        $radLat1=deg2rad($lat1);//deg2rad()函数将角度转换为弧度
        $radLat2=deg2rad($lat2);
        $radLng1=deg2rad($lng1);
        $radLng2=deg2rad($lng2);
        $a=$radLat1-$radLat2;
        $b=$radLng1-$radLng2;
        $s=2*asin(sqrt(pow(sin($a/2),2)+cos($radLat1)*cos($radLat2)*pow(sin($b/2),2)))*6378.137*1000;
        return $s;
    }*/
    /**
     * [getAround 获取周围活动]
     * @return [type] [返回标题、图片、活动id]
     */
    public function getAround(){
        if($this->flag==1)
        {
            self::setfake($this->logTime);
        }
        $result_user = $this->solrUser->select(array('q'=>"id:$this->userid"));

        $usercity = $result_user['data']['response']['docs'][0]['usercity'];
        $operation = $result_user['data']['response']['docs'][0]['operation'];
        $content = $result_user['data']['response']['docs'][0]['title'];
        $types = explode('-',$operation);
        $type = $types[1];
        // if($type==5){
        //             $result=self::moreCat();
        //             $num=count($result['title']);
        //             $resultItem="";
        //             for($i=0;$i<$num;$i++)
        //             {   
        //                  $resultItem .=sprintf($this->textItem,$result['title'][$i],$result['imageurl'][$i],$result['url'][$i]);
        //             }
        //             $resultStr = sprintf($this->textImg,$fromUsername,$toUsername,
        //             $time,$num,$resultItem);
        //             $answer['ok']=1;
        //             $answer['str']=$resultItem;
        //             $answer['num']=$num;

        //             return $answer;
        // }
        $latitude = $result_user['data']['response']['docs'][0]['latitude'];
        $longitude = $result_user['data']['response']['docs'][0]['longitude'];

        $params=array();
        $params['q'] = "(location_description:$usercity)";

        if($type==1)
        {
            $params['q'] = "tags:$content OR title:$content";
        }
        else if($type==2)
        {
            $params['q'] = $params['q']." AND tags:讲座";
        }
        else if($type==3)
        {
            $params['q'] = $params['q']." AND tags:音乐 戏剧 电影";
        }
        else if($type==4)
        {
            $params['q'] = $params['q']." AND tags:美食";
        }
        else if($type==5)
        {
            $params['q'] = $params['q']." AND tags:亲子";   
        }
        // $params['fq']="eventdate:[NOW TO *]";
        $params['sort'] = "geodist()+asc";
        $params['sfield'] = "location";
        $params['pt'] = $latitude.",".$longitude;
        $params['start'] = $this->page;
        $params['rows'] = 20;
        $date = date('Y-m-d,H:i:s',time()-8*60*60);
        $time = explode(',',$date);
        $shijian = $time[0]."T".$time[1]."Z";
        $time2 = date('Y-m-d',time()+7*24*60*60)."T15:59:59Z";
        $params['fq'] = "happentime:[$shijian TO *]";
        // $params['fq']="{!geofilt}";
        $params['fl']="id,title,imageurl,eventdate,location";
        // $params['body']['query']['bool']['must'][]['match']['area']=$userarea;
        // 
        $resultAround=$this->solrEvent->select($params);

        $answer['temp']=array();
        $answer['img'] = array();
        $j=0;
        $i=0;

        if($resultAround['data']['response']['numFound']==0)
        {
            $answer['error']="您附近没有任何活动";
        }
        else{
            $displayNum = 4;
            
            if($resultAround['data']['response']['numFound']<4)
            {
                $displayNum = $resultAround['data']['response']['numFound'];
            }

            for($i=0;($i<20&&$j<$displayNum);$i++)
            {
                self::filterHtml($resultAround['data']['response']["docs"][$i]["title"]);
                if($resultAround['data']['response']["docs"][$i]["title"]=="")
                    continue;
                if(in_array(trim($resultAround['data']['response']["docs"][$i]["title"]),$answer['temp']))
                {
                    if($displayNum<4)
                    {
                        $j++;
                    }
                    continue;
                }
                if(empty($resultAround['data']['response']["docs"][$i]["title"]))
                {
                    continue;
                }
                if(self::filterImg($resultAround['data']['response']["docs"][$i]["imageurl"],$answer['img']) == 1){
                    continue;
                }
                else{
                    $answer['img'][] = $resultAround['data']['response']["docs"][$i]["imageurl"];
                }
                
                $j++;
                $answer['temp'][]=trim($resultAround['data']['response']["docs"][$i]["title"]);
                $location=explode(',',$resultAround['data']['response']["docs"][$i]["location"]);
                
                if(!empty($this->latitude))
                {
                    $getLocation="&origins=".(string)$this->latitude.",".(string)$this->longitude;
                    $aimLocation="&destinations=".(string)$location[0].",".(string)$location[1];
                    $apiUrl="http://api.map.baidu.com/direction/v1/routematrix?ak=DDa0545d1e03780687ead34bc4701ee1&output=xml&tatics=11";
                    $resUrl=$apiUrl.$getLocation.$aimLocation;
                    
                    $rtn1=file_get_contents($resUrl);
                    $rtnCnt=simplexml_load_string($rtn1,'SimpleXMLElement',LIBXML_NOCDATA);
                    $rtnDistance="    ".(string)$rtnCnt->result->elements->distance->text;
                }
                $answer['title'][]=trim($resultAround['data']['response']["docs"][$i]["title"])."\n".$resultAround['data']['response']["docs"][$i]["eventdate"].((isset($rtnDistance))?$rtnDistance:"");
                $answer['imageurl'][]=$resultAround['data']['response']['docs'][$i]['imageurl'];
                $answer['eventid'][]=$resultAround['data']['response']['docs'][$i]['id'];
            }
            $this->solrUser->update(array('id'=>$this->userid,'page'=>array('set'=>($this->page+$i-$j))));       
        } 
        $this->logMy();

        if(count($answer['title'])==0)
        {
            $answer = array();
            $answer['error']="您附近没有任何相关活动";
            return $answer; 
        }

        if($this->page==0 && $type==1){
            $answer['imageurl'][count($answer['title'])-1]="http://123.56.93.141/img/help.jpg";
            $answer['eventid'][count($answer['title'])-1]="0";
            $answer["title"][count($answer['title'])-1]="附近兴趣返回的是在周围的您所搜索的活动，若想更换，可先检索关键词再点击该附近兴趣！";
        }
        return $answer;
    }
    /**
     * [moreCat 获取更多类型信息]
     * @return [type] [description]
     */
    public function moreCat(){
        // $rsp['title'][]="找乐365更多类型";
        // // $rsp['imageurl'][]="http://123.56.93.141/img/logo.png";
        // // $rsp['url'][]="http://www.zhaole365.com";
        // // $rsp['title'][]="IT";
        // $rsp['imageurl'][]="http://123.56.93.141/img/it.jpg";
        // $rsp['url'][]="http://www.zhaole365.com/#it";
        $rsp['title'][]="创业";
        $rsp['imageurl'][]="http://123.56.93.141/img/business.jpg";
        $rsp['url'][]="http://www.zhaole365.com/#innovation";
        $rsp['title'][]="聚会";
        $rsp['imageurl'][]="http://123.56.93.141/img/meeting.jpg";
        $rsp['url'][]="http://www.zhaole365.com/#meetup";
        $rsp['title'][]="户外";
        $rsp['imageurl'][]="http://123.56.93.141/img/outdoor.jpg";
        $rsp['url'][]="http://www.zhaole365.com/#outing";
        $rsp['title'][]="体育";
        $rsp['imageurl'][]="http://123.56.93.141/img/sports.jpg";
        $rsp['url'][]="http://www.zhaole365.com/#exercise";
        $rsp['title'][]="亲子";
        $rsp['imageurl'][]="http://123.56.93.141/img/family.jpg";
        $rsp['url'][]="http://www.zhaole365.com/#child";        
        $rsp['title'][]="展览";
        $rsp['imageurl'][]="http://123.56.93.141/img/display.jpg";
        $rsp['url'][]="http://www.zhaole365.com/#exbition";
        $rsp['title'][]="演出";
        $rsp['imageurl'][]="http://123.56.93.141/img/show.jpg";
        $rsp['url'][]="http://www.zhaole365.com/#entertaiment";
        $rsp['title'][]="电影";
        $rsp['imageurl'][]="http://123.56.93.141/img/movie.jpg";
        $rsp['url'][]="http://www.zhaole365.com/#film";
        return $rsp;
    }
    /**
     * [responseEvent 对msg类型为event的消息进行回复]
     * @param  [type] $postObj      [返回的微信消息的对象]
     * @param  [type] $fromUsername [来源用户的id]
     * @param  [type] $toUsername   [目标用户的id]
     * @param  [type] $time         [时间戳]
     * @return [type]               [返回已经格式的回复信息]
     */
    public function responseEvent($postObj,$fromUsername,$toUsername,$time){
        $eventType = $postObj->Event;
        if($eventType=='CLICK')
        {
            $eventKey = explode("|",$postObj->EventKey);
            if($eventKey[0]=="group")
            {
                switch ($eventKey[1]) {
                    case '1':
                      $result = self::getGroup('1');
                      break;
                    case '2':
                      $result = self::getGroup('2');
                      break;
                    case '4':
                      $result = self::saveGroup();
                      break;                                                                            
                    default:
                      break;
                }
                if(isset($result['error'])){
                    $resultStr =  sprintf($this->textTpl,$fromUsername,$toUsername,$time,$result['error']);
                }
                else{
                    $num = count($result['title']);
                    $resultItem = "";

                    for($i=0;$i<$num;$i++)
                    {   
                        $url="http://123.56.93.141/s_group.php?groupid=".$result['groupid'][$i]."&userid=".$this->userid;
                        $resultItem .=sprintf($this->textItem,$result['title'][$i],$result['imageurl'][$i],$url);
                    }

                    $resultStr = sprintf($this->textImg,$fromUsername,$toUsername,
                    $time,$num,$resultItem);
                }
            }
            else if($eventKey[0]=="around")
            {
                $this->solrUser->update(array('id'=>$this->userid,'operation'=>array('set'=>"around-".$eventKey[1]),'page'=>array('set'=>0)));
                $result=self::getAround();
                
                if(isset($result['error']))
                {
                    $resultStr =  sprintf($this->textTpl,$fromUsername,$toUsername,$time,$result['error']);
                }
                else if(isset($result['ok'])){
                    $resultStr=sprintf($this->textImg,$fromUsername,$toUsername,$time,$result['num'],$result['str']);
                }
                else
                {
                    $num=count($result['title']);
                    $resultItem="";

                    for($i=0;$i<$num;$i++)
                    {   
                        $url="http://".URL_PATH."?eventid=".$result['eventid'][$i]."&userid=".$this->userid.(empty($this->latitude)?"":("&lng=".$this->longitude."&lat=".$this->latitude));             
                        if($result["eventid"][$i]=="0")
                            $url="";
                        $resultItem .=sprintf($this->textItem,$result['title'][$i],$result['imageurl'][$i],$url);
                    }

                    $resultStr = sprintf($this->textImg,$fromUsername,$toUsername,
                    $time,$num,$resultItem);
                }
            }
            else if($eventKey[0]=="menu")
            {
                switch($eventKey[1])
                {
                    case 'today':
                        $this->solrUser->update(array('id'=>$this->userid,'operation'=>array('set'=>"today"),'page'=>array('set'=>0)));
                        $result=self::getToday();

                        if(isset($result['error']))
                        {
                            $resultStr =  sprintf($this->textTpl,$fromUsername,$toUsername,$time,$result['error']);
                        }
                        else
                        {
                            $num = count($result['title']);
                            $resultItem = "";

                            for($i=0;$i<$num;$i++)
                            {   
                                $url = "http://".URL_PATH."?eventid=".$result['eventid'][$i]."&userid=".$this->userid.(empty($this->latitude)?"":("&lng=".$this->longitude."&lat=".$this->latitude));               
                                $resultItem .=sprintf($this->textItem,$result['title'][$i],$result['imageurl'][$i],$url);
                            }

                            $resultStr = sprintf($this->textImg,$fromUsername,$toUsername,
                            $time,$num,$resultItem);
                        }
                        break;
                    case 'week':
                        $this->solrUser->update(array('id'=>$this->userid,'operation'=>array('set'=>"week"),'page'=>array('set'=>0)));
                        $result = self::getWeek();

                        if(isset($result['error']))
                        {
                            $resultStr =  sprintf($this->textTpl,$fromUsername,$toUsername,$time,$result['error']);
                        }
                        else
                        {
                            $num = count($result['title']);
                            $resultItem = "";
                        for($i=0;$i<$num;$i++)
                        {   
                           $url = "http://".URL_PATH."?eventid=".$result['eventid'][$i]."&userid=".$this->userid.(empty($this->latitude)?"":("&lng=".$this->longitude."&lat=".$this->latitude));             
                           $resultItem .=sprintf($this->textItem,$result['title'][$i],$result['imageurl'][$i],$url);
                        }

                        $resultStr = sprintf($this->textImg,$fromUsername,$toUsername,
                        $time,$num,$resultItem);
                        }
                        break;
                    case 'more':
                        $result = self::moreCat();
                        $num = count($result['title']);
                        $resultItem = "";

                        for($i=0;$i<$num;$i++)
                        {   
                             $resultItem .=sprintf($this->textItem,$result['title'][$i],$result['imageurl'][$i],$result['url'][$i]);
                        }

                        $resultStr = sprintf($this->textImg,$fromUsername,$toUsername,
                        $time,$num,$resultItem);
                        break;                         
                    case 'save':
                        $result = self::getSave();

                        if(isset($result['error']))
                        {
                          $resultStr =  sprintf($this->textTpl,$fromUsername,$toUsername,$time,$result['error']);
                        }
                        else
                        {
                            $num=count($result['title']);
                            $resultItem="";
                            for($i=0;$i<$num;$i++)
                            {   
                                $url = "http://".URL_PATH."?eventid=".$result['eventid'][$i]."&userid=".$this->userid.(empty($this->latitude)?"":("&lng=".$this->longitude."&lat=".$this->latitude));                
                                $resultItem .=sprintf($this->textItem,$result['title'][$i],$result['imageurl'][$i],$url);
                            }
                            $resultStr = sprintf($this->textImg,$fromUsername,$toUsername,
                            $time,$num,$resultItem);
                        }
                        break;
                    case 'next':
                        $resultStr=self::nextPage($fromUsername,$toUsername,$time);
                        break;
                    case 'contact':
                        $resultOpe=$this->solrUser->select(array('q'=>"id:$this->userid"));
                        $ope=$resultOpe['data']['response']['docs'][0]['operation'];

                        if($ope=="contact")
                        {
                            $this->solrUser->update(array('id'=>$this->userid,'operation'=>array('set'=>"search")));
                            $answer="您已取消对话模式";
                        }
                        else
                        {
                            $this->solrUser->update(array('id'=>$this->userid,'operation'=>array('set'=>"contact")));
                            $answer="您已记录为对话模式";
                        }

                        $resultStr=sprintf($this->textTpl,$fromUsername,$toUsername,$time,$answer);
                        break;
                    default:
                        $resultStr=sprintf($this->textTpl,$fromUsername,$toUsername,$time,$this->default);
                        break;                                                                      
                }
             }
        }
        else if($eventType=="location_select")
        {
            // $getX=(string)$postObj->SendLocationInfo->Location_X;
            // $getY=(string)$postObj->SendLocationInfo->Location_Y;
            // $getlocation=(string)$postObj->SendLocationInfo->Label;
            // self::setAddress($getlocation,$getX,$getY);
            $this->solrUser->update(array('id'=>$this->userid,'operation'=>array('set'=>"around-".$postObj->EventKey),'page'=>array('set'=>0)));
            // $result=self::getAround();
            // if(isset($result['error']))
            // {
            //     $resultStr =  sprintf($this->textTpl,$fromUsername,$toUsername,$time,$result['error']);
            // }
            // else
            // {
            //     $num=count($result['title']);
            //     $resultItem="";
            //     for($i=0;$i<$num;$i++)
            //     {   
            //         $url="http://".URL_PATH."/zlevent/".$result['eventid'][$i]."?wid=".$this->userid.(empty($this->latitude)?"":("&lng=".$this->longitude."&lat=".$this->latitude));
            //         $resultItem .=sprintf($this->textItem,$result['title'][$i],$result['imageurl'][$i],$url);
            //     }
            //     $resultStr = sprintf($this->textImg,$fromUsername,$toUsername,
            //     $time,$num,$resultItem);
            // }
            // $content="您好,在详细信息里您将获得位置服务!";
            // $resultStr = sprintf($this->textTpl,$fromUsername,$toUsername,$time,$content);
        }
        else if($eventType=="subscribe")
        {
            $resultStr = sprintf($this->textTpl,$fromUsername,$toUsername,$time,$this->welcome);
        }
        else if($eventType=='LOCATION')
        {
            $getX = (string)($postObj->Latitude);
            $getY = (string)($postObj->Longitude);
            self::setlat($getX,$getY);
        }
        return $resultStr;
    }
    /**
     * [responseMsg 处理来源信息并回复]
     * @return [type] [返回格式化的回复信息]
     */
    public function responseMsg() {
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"]; //获取POST数据
        $postObj = simplexml_load_string($postStr,'SimpleXMLElement',LIBXML_NOCDATA);
        // $errno = 0; 
        // $errstr = "";
        // $timeout=5; 
        // $fp=fsockopen('123.56.93.141',80,$errno,$errstr,$timeout);
        // fputs($fp,"POST /test.php\r\n");
        // fclose($fp);
        //---------- 接 收 数 据 ---------- //
        $fromUsername = $postObj->FromUserName;
        $createTime = $postObj->CreateTime;
        $this->logTime = $createTime;
        $this->userid = (string)$fromUsername;

        self::getUser();
        
        $toUsername = $postObj->ToUserName; //获取接收方账号
        $time = time(); //获取当前时间戳
        $msgtype = $postObj->MsgType;
        $voice = $postObj->Recognition;
        // foreach($postObj as $post){
        //     $contents=$contents.$post;
        // }
        // $mysql = new SaeMysql();
        // $sql = "INSERT  INTO `wechat` (`address`) VALUES ('".$msgtype.$event."')";
        // $mysql->runSql($sql);
        // $mysql->closeDb();
        if($msgtype=="location")
        {
            $getX = (string)($postObj->Location_X);
            $getY = (string)($postObj->Location_Y);
            $getlocation = (string)($postObj->Label);

            self::setAddress($getlocation,$getX,$getY);
            // $this->solrUser->update(array('id'=>$this->userid,'operation'=>array('set'=>"around0"),'page'=>array('set'=>0)));
            $result=self::getAround();
            
            if(isset($result['error']))
            {
                $resultStr =  sprintf($this->textTpl,$fromUsername,$toUsername,$time,$result['error']);
            }
            else if(isset($result['ok'])){
                    $resultStr=sprintf($this->textImg,$fromUsername,$toUsername,
                    $time,$result['num'],$result['str']);
            }
            else
            {
                $num=count($result['title']);
                $resultItem="";
                for($i=0;$i<$num;$i++)
                {                       
                    // $url="http://".URL_PATH."/zlevent/".$result['eventid'][$i]."?wid=".$this->userid.(empty($this->latitude)?"":("&lng=".$this->longitude."&lat=".$this->latitude));
                    $url="http://".URL_PATH."?eventid=".$result['eventid'][$i]."&userid=".$this->userid.(empty($this->latitude)?"":("&lng=".$this->longitude."&lat=".$this->latitude));             
                    $resultItem .=sprintf($this->textItem,$result['title'][$i],$result['imageurl'][$i],$url);
                }
                    $resultStr = sprintf($this->textImg,$fromUsername,$toUsername,$time,$num,$resultItem);
            }
        }
        else if($msgtype=="event")
        {
            // $content="您好,在详细信息里您将获得位置服务!";
            // $resultStr=sprintf($this->textTpl,$fromUsername,$toUsername,$time,$this->welcome);
            $resultStr=self::responseEvent($postObj,$fromUsername,$toUsername,$time);//call back the function that deal with event
        }
        else if($msgtype=="voice")
        {
            $this->solrUser->update(array('id'=>$this->userid,'operation'=>array('set'=>"search"),'page'=>array('set'=>0),'title'=>array('set'=>$voice)));
            $page_result=$this->solrUser->select(array('q'=>"id:$this->userid"));
            $keyloc=$page_result['data']['response']['docs'][0]['usercity'];

            $result=self::getBird($voice,$keyloc);
            
            if(isset($result['error'])){
                $strerr="";
                $strerr .=sprintf($this->textItem,$voice,"http://123.56.93.141/img/background.jpg","");
                $strerr .=sprintf($this->textItem,$result['error'],"","");
                $resultStr = sprintf($this->textImg,$fromUsername,$toUsername,
                $time,2,$strerr);
            }
            else {  
                $num=count($result['title']);
                $resultItem="";
                $resultItem .=sprintf($this->textItem,$voice,"http://123.56.93.141/img/background.jpg","");
                
                for($i=0;$i<$num;$i++)
                {   
                 $url="http://".URL_PATH."?eventid=".$result['eventid'][$i]."&userid=".$this->userid.(empty($this->latitude)?"":("&lng=".$this->longitude."&lat=".$this->latitude));                 
                 $resultItem .=sprintf($this->textItem,$result['title'][$i],$result['imageurl'][$i],$url);
                }

                $resultStr = sprintf($this->textImg,$fromUsername,$toUsername,
                $time,$num+1,$resultItem);
            }
        }
        else if($msgtype=="text")
        { 
            if($this->flag==1)
            {
                self::setfake($createTime);
            }

            $keyContent = trim($postObj->Content); //获取消息内容
            $resultOpe=$this->solrUser->select(array('q'=>"id:$this->userid"));
            $ope=$resultOpe['data']['response']['docs'][0]['operation'];
            
            if($ope=="contact")
            {
                $comunication=explode('@',$keyContent);
                if(!isset($comunication[1]))
                {
                    $comunication[1]="";
                    self::getContact($comunication[1],$comunication[0],$createTime);
                }
                else
                    self::getContact($comunication[0],$comunication[1],$createTime);
                $resultStr="";
            }
            else
            {
                $this->solrUser->update(array('id'=>$this->userid,'operation'=>array('set'=>"search"),'page'=>array('set'=>0),'title'=>array('set'=>$keyContent)));

                $page_result=$this->solrUser->select(array('q'=>"id:$this->userid"));
                $keyloc=$page_result['data']['response']['docs'][0]['usercity'];

                $result=self::getBird($keyContent,$keyloc);

                if(isset($result['error']))
                {
                    $resultStr=sprintf($this->textTpl,$fromUsername,$toUsername,$time,$result['error']);
                }
                else {  
                    $num=count($result['title']);
                    $resultItem="";
                    for($i=0;$i<$num;$i++)
                    {   
                        $url="http://".URL_PATH."?eventid=".$result['eventid'][$i]."&userid=".$this->userid.(empty($this->latitude)?"":("&lng=".$this->longitude."&lat=".$this->latitude));                 
                        $resultItem .=sprintf($this->textItem,$result['title'][$i],$result['imageurl'][$i],$url);
                    }
                        $resultStr = sprintf($this->textImg,$fromUsername,$toUsername,
                        $time,$num,$resultItem);
                    }
            }
        }
            echo $resultStr; //输出结果
    }
}?>
