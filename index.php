<?php

/**
 * @SWG\Swagger(
 *   @SWG\Info(
 *     title="Dictionary of RDF Prefixes",
 *     version="1.0.0"
 *   )
 * )
 */

define("HOSTNAME", 'http://prefix.chemskos.com');

header('Access-Control-Allow-Origin: *');




require_once 'lib/limonade.php';


function before()
{
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
}

function getUserInfo()
{
    $user = new stdClass;

    $headers = apache_request_headers();
    if (empty($headers['Authorization'])) {
        // $user->id=null;
        return $user;
    }

    if (empty($headers['Server'])) {
        // $user->id=null;
        return $user;
    }
    $user->oauthServer=$headers['Server'];


    switch ($user->oauthServer) {
        case 'Google':
            $url = 'https://www.googleapis.com/oauth2/v1/userinfo';
            $id='id';
            break;
        case 'github':
            $url = 'https://api.github.com/user';
            $id='id';
            break;
        case 'Facebook':
            $url = 'https://graph.facebook.com/v2.12/me?fields=id,first_name,gender,last_name,link,locale,name,timezone,updated_time,verified,email';
            $id='id';
            break;
        case 'Reddit':
            $url = 'https://oauth.reddit.com/api/v1/me.json';
            $id='name';
            break;
        // case 'Twitter':
        //     $url = 'https://api.twitter.com/oauth2/token';
        //     $id='id';
        //     break;
        default:
            // $user->id=null;
            // return $user;

    }

    $response = curl($url, 'GET', '', true);
    $php = json_decode($response);
    //  print_r($php);


    $user->id=$php->$id;
    return $user;
}

function search_by($key, $value)
{

    $data_string = '{
        "selector": {
            "' . $key . '": {"$eq": "' . $value . '"}
        },
        "fields": [
            "_id",
            "_rev",
            "namespace",
            "prefix",
            "pluses",
            "minuses",
            "score"
        ]
    }';
    return $data_string;
}
function curl($url, $method, $data_string, $withOauth)
{

    $headers = array(
        'Content-Type: application/json',
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/29.0.1521.3 Safari/537.36',
        'Content-Length: ' . strlen($data_string));
    if ($withOauth) {

        $headersRequest = apache_request_headers();

        if (empty($headersRequest['Authorization'])) {
            return false;
        }
        array_push($headers, 'Authorization: ' . $headersRequest['Authorization']);
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    return $response;

}




    /**
     * @SWG\Post(
     *     path="/api/{namespace}",
     *     summary="Adding a plus or minus",
     *     description="Operation allows plus or minus the namespace",
     *     operationId="PostNamespace",
     *     produces={"application/json+ld"},
     *     @SWG\Parameter(
 *         name="authorization",
 *         in="header",
 *         description="Press access-token",
 *         required=true,
 *         type="string",
 *     ),
     *     @SWG\Parameter(
     *         description="Namespace",
     *         in="path",
     *         name="namespace",
     *         required=true,
     *         type="string",
     *         format="string",
     *     ),
     *         @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="JSON object containing a plus or minus ranking",
     *         required=true,
     *   enum={"pending", "failed", "completed"},
     *       @SWG\Schema(
	 *       type="object",
	 *       @SWG\Property(property="@context", type="object",@SWG\Property(property="pres", type="string")),
     * 	     @SWG\Property(property="@id", type="string"),
     *       @SWG\Property(property="pres:ranking", type="string"),
	 *         )
     *     ),
     *     @SWG\Response(
     *         response=201,
     *         description="Successful operation",
     *     ),
     *     @SWG\Response(
     *         response="409",
     *         description="Plus or minus already exists"
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Invalid JSON structure"
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */


dispatch_post('/*:/**', function () {

// body z requesta
$obj = file_get_contents("php://input", "r");
$tresc = json_decode($obj, true);

$type=$tresc['type'];

    $currentNamespace = params(0) .':/' . params(1);

    if((substr(params(1), -1))!='/')
    $currentNamespace.='#';


    echo $currentNamespace;


    // echo params(1);
// echo $currentNamespace;

    $datajson = search_by('namespace', $currentNamespace);
    $currentDocument = curl(HOSTNAME.':5984/vocabulary/_find', 'POST', $datajson, false);


    $phpnamespace = json_decode($currentDocument);
    $prefix = $phpnamespace->docs[0]->prefix;


    $user = getUserInfo();

    $clickedNamespaces = whichClicked($prefix, $user->oauthServer, $user->id);

    $json_to_php = json_decode($currentDocument);

    
        $authServer = $user->oauthServer;


        // print_r($clickedNamespaces);


if($type=='plus'){


    if(isset($clickedNamespaces->minuses))
    {
    if(in_array($currentNamespace, $clickedNamespaces->minuses))
    halt(409, 'Conflict');
    }

    if (isset($clickedNamespaces->plus)) {
        halt(409, 'Conflict');

    }


        $json_to_php->docs[0]->pluses->$authServer[] = (string) $user->id;
        $json_to_php->docs[0]->score += 1;

        $datajson = json_encode($json_to_php->docs[0]);


        $json3 = curl(HOSTNAME.':5984/vocabulary/' . $json_to_php->docs[0]->_id, 'PUT', $datajson, false);

        halt(201, "CREATED");

}
else if($type=='minus'){


    if(isset($clickedNamespaces->minuses))
    {
    if(in_array($currentNamespace, $clickedNamespaces->minuses))
    halt(409, 'Conflict');
    }

   
    if (isset($clickedNamespaces->plus)) {
        if($clickedNamespaces->plus==$currentNamespace){
        halt(409, 'Conflict');}

    } 


        $json_to_php->docs[0]->minuses->$authServer[] = (string) $user->id;
        $json_to_php->docs[0]->score -= 1;

        $datajson = json_encode($json_to_php->docs[0]);


        $json3 = curl(HOSTNAME.':5984/vocabulary/' . $json_to_php->docs[0]->_id, 'PUT', $datajson, false);

        halt(201, "CREATED");


}
else{
    halt(400, "BAD REQUEST");
}




    /**
     * @SWG\Delete(
     *     path="/api/{namespace}",
     *     summary="Adding a plus or minus",
     *     description="Operation allows plus or minus the namespace",
     *     operationId="DeleteNamespace",
     *     produces={"application/json+ld"},
     *     @SWG\Parameter(
 *         name="authorization",
 *         in="header",
 *         description="Press access-token",
 *         required=true,
 *         type="string",
 *     ),
     *     @SWG\Parameter(
     *         description="Namespace",
     *         in="path",
     *         name="namespace",
     *         required=true,
     *         type="string",
     *         format="string"
     *     ),
     *         @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="JSON object containing a plus or minus ranking",
     *         required=true,
     *       @SWG\Schema(
	 *       type="object",
	 *       @SWG\Property(property="@context", type="object",@SWG\Property(property="pres", type="string")),
     * 	     @SWG\Property(property="@id", type="string"),
     *       @SWG\Property(property="pres:ranking", type="string"),
	 *         )
     * ),
     *     @SWG\Response(
     *         response=200,
     *         description="Successful operation",
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Invalid JSON structure"
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Plus or minus not exists"
     *     ),
     *     @SWG\Response(
     *         response="401",
     *         description="You're not logged in"
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */


});

dispatch_delete('/*:/**/', function () {

    // body z requesta
$obj = file_get_contents("php://input", "r");
$tresc = json_decode($obj, true);

$type=$tresc['type'];

if($type=='plus'){
$score=-1;
$removeFrom='pluses';

}
elseif($type=='minus'){
$score=1;
$removeFrom='minuses';

}
else{
    halt(400, "BAD REQUEST");
}

$currentNamespace = params(0) .':/' . params(1);

if((substr(params(1), -1))!='/')
$currentNamespace.='#';


    $dataJson = search_by('namespace',$currentNamespace);
    $currentDocument = curl(HOSTNAME.':5984/vocabulary/_find', 'POST', $dataJson, false);
    $phpnamespace = json_decode($currentDocument);
    $prefix = $phpnamespace->docs[0]->prefix;

    $json2 = curl(HOSTNAME.'/api/?/' . $prefix, 'PATCH', '', false);

    $json_to_php = json_decode($currentDocument);

    $user = getUserInfo();

    $clickedNamespaces = whichClicked($prefix, $user->oauthServer, $user->id);

    if (!property_exists($user, 'id')) {
        halt(401, 'UNAUTHORIZED');
    } else {
        $server=$user->oauthServer;

        if (($key = array_search($user->id, $json_to_php->docs[0]->$removeFrom->$server)) !== false) {   
            unset($json_to_php->docs[0]->$removeFrom->$server[$key]);
            $json_to_php->docs[0]->score += $score;

            $datajson = json_encode($json_to_php->docs[0]);
            $json3 = curl(HOSTNAME.':5984/vocabulary/' . $json_to_php->docs[0]->_id, 'PUT', $datajson, false);

        }
        else{
            halt(404, 'NOT FOUND');

        }


    }

});


    /**
     * @SWG\Get(
     *     path="/api/{namespace}",
     *     summary="Details of the namespace",
     *     description="Returns a single namespace",
     *     operationId="getNamespace",
     *     produces={"application/json+ld"},
     *     @SWG\Parameter(
     *         description="Namespace to return",
     *         in="path",
     *         name="namespace",
     *         required=true,
     *         type="string",
     *         format="string"
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Successful operation",
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Namespace not found"
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */

dispatch_get('/*:/**', function () {


    $currentNamespace = params(0) .':/' . params(1);

    if((substr(params(1), -1))!='/')
    $currentNamespace.='#';



    $datajson = search_by('namespace', $currentNamespace);
    $response = curl(HOSTNAME.':5984/vocabulary/_find', 'POST', $datajson, false);
    $json_to_php = json_decode($response);

    if (isset($json_to_php->docs[0])) {

$printJson='{
	"@context": {
		"name": "http://schema.org/name",
		"description": "http://schema.org/description",
		"image": {
			"@id": "http://schema.org/image",
			"@type": "@id"
		},
		"geo": "http://schema.org/geo",
		"latitude": {
			"@id": "http://schema.org/latitude",
			"@type": "xsd:float"
		},
		"longitude": {
			"@id": "http://schema.org/longitude",
			"@type": "xsd:float"
		},
		"xsd": "http://www.w3.org/2001/XMLSchema#"
	},
	"prefix": "'. $json_to_php->docs[0]->prefix .'",
	"score": '. $json_to_php->docs[0]->score .'
}';

echo $printJson;


    } else {
        halt(404, 'Not Found');
    }

}
);

    /**
     * @SWG\Post(
     *     path="/api/prepare",
     *     summary="Return modified document Turtle",
     *     description="a method that modifies the turtle file to input the needed turtle document",
     *     operationId="PostPrepare",
     *     produces={"text/turtle"},
     *     @SWG\Parameter(
     *         description="file",
     *         in="formData",
     *         name="file",
     *         required=true,
     *         type="file",
     *         format="turtle"
     *     ),
     *     @SWG\Response(
     *         response=201,
     *         description="Successful operation",
     *     ),
     *     @SWG\Response(
     *         response="406",
     *         description="at least one prefix was not found"
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */

dispatch_post('/prepare', function () {
    

    function findBase($file){
        preg_match('/(@base|BASE).+<(.*?)>.+/', $file, $res);
        if(isset($res[2])){
        $base=$res[2];
        return $base;
        }
        else return '';
        }
        function definedPrefixes($file){
            preg_match_all('/(@prefix|PREFIX)\s(\w+?):/', $file, $res);
            return $res[2];
        }
        
        function usedPrefixes($file){
            preg_match_all('/\s(\w+?):\w+/', $file, $res);
        
            $prefixes = array_unique($res[1]);
        return $prefixes;
        }
        function undefinedPrefixes($file){
                $usedPrefixes = usedPrefixes($file);
                $definedPrefixes = definedPrefixes($file);
                return array_diff($usedPrefixes, $definedPrefixes);
            }


function getUndefinedNamespaces($file){

    $prefixes=undefinedPrefixes($file);



    foreach($prefixes as $one){


        $ch = curl_init('http://212.237.58.110/api/?/'.$one.'/formats/turtle');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_POSTFIELDS, '');
    curl_setopt($ch, CURLOPT_POSTFIELDS, '');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: text/plain')); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);



    if (!curl_errno($ch)) {
        switch ($http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
          case 200:
          $namespaces[] = substr($response, 3);
            break;
          default:
            return false;
        }
      }
      curl_close($ch);
    }



    if (empty($namespaces)) {
return null;
    }
    else{
        return $namespaces;
    }




}



if(!isset($_FILES['file']['tmp_name'])){

halt(400,"Bad Request");

}
    $file = file_get_contents($_FILES['file']['tmp_name']);



include_once("../vendor/semsol/arc2/ARC2.php");
include_once("../vendor/semsol/arc2/serializers/ARC2_JSONLDSerializer.php");







$namespaces=getUndefinedNamespaces($file);

$finalfile='';


if($namespaces==false){
    halt(406, "Not Acceptable");
}
else if (!empty($namespaces)){
foreach($namespaces as $one){
    $finalfile.=$one.PHP_EOL;
}
}
$finalfile.=$file;
// echo $finalfile;




// readfile($finalfile);




$headers = apache_request_headers();


if (strpos($headers['Accept'], 'text/turtle') !== false){
header("Content-Type: text/turtle");
header('Content-Disposition: attachment; filename="example.ttl"');
header("Content-Length: " . strlen($finalfile));
header("Set-Cookie: fileDownload=true; path=/");
echo $finalfile;
}
elseif (strpos($headers['Accept'], 'application/ld+json') !== false){

    $base=findBase($finalfile);
    $finalfile = preg_replace('/(@base|BASE).+/', '', $finalfile);
    include_once("../vendor/semsol/arc2/ARC2.php");

    
$parser = ARC2::getRDFParser();
$parser->parse($base,$finalfile);
$index = $parser->getSimpleIndex();
$ser = ARC2::getJSONLDSerializer();
$doc = $ser->getSerializedIndex($index);
echo $doc;

}
elseif (strpos($headers['Accept'], 'application/n-triples') !== false){

    $base=findBase($finalfile);
    $finalfile = preg_replace('/(@base|BASE).+/', '', $finalfile);
    include_once("../vendor/semsol/arc2/ARC2.php");


    $parser = ARC2::getRDFParser();
    $parser->parse($base,$finalfile);
    $triples = $parser->getTriples();
    $doc = $parser->toNTriples($triples);
    echo $doc;
    }
    elseif (strpos($headers['Accept'], 'application/rdf+xml') !== false){

        $base=findBase($finalfile);
        $finalfile = preg_replace('/(@base|BASE).+/', '', $finalfile);
        include_once("../vendor/semsol/arc2/ARC2.php");


        $parser = ARC2::getRDFParser();
        $parser->parse($base,$finalfile);
        $triples = $parser->getTriples();
        $doc = $parser->toRDFXML($triples);
        echo $doc;
        }

    




// exit;
// file_put_contents(($_FILES['file']['name']), $finalfile);




});



    /**
     * @SWG\Get(
     *     path="/api/popular",
     *     summary="Details of popular prefixes",
     *     description="Returns a popular prefixes",
     *     operationId="getPopular",
     *     produces={"application/json+ld"},
     *     @SWG\Parameter(
     *         description="Namespace to return",
     *         in="path",
     *         name="namespace",
     *         required=true,
     *         type="string",
     *         format="string"
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Successful operation",
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */


dispatch_get('/popular', function () {


    function generatePopular($prefixes,$pagination){

        $headers = apache_request_headers();
    
        if (strpos($headers['Accept'], 'text/turtle') !== false){
            header('Content-Type: text/turtle');
            generatePopularTurtle($prefixes,$pagination);}
            else{
                header('Content-Type: application/ld+json');
                generatePopularJSONLD($prefixes,$pagination);
                // generatePopularTurtle($prefixes,$pagination);

            }
    }
    
    function generatePopularTurtle($prefixes,$pagination){
        $buildDocument='@prefix hydra: <http://www.w3.org/ns/hydra/core#> .
@prefix pres: <http://ii.uwb.edu.pl/prefix_resolver#> .
            
        [] <pres:prefixes> (';
    
            foreach ($prefixes as $object) {
            
    
    $buildDocument.='
        [
        <pres:prefix> "'.$object->prefix.'" ;
        <pres:score> '.$object->score.' ;
        ]'; 
            }
            $buildDocument.='
        );
            hydra:view <'.$pagination->current.'> .
       
            <'.$pagination->current.'>
          a <http://www.w3.org/ns/hydra/core#PartialCollectionView> ;
          hydra:first "'.$pagination->first.'" ;
          ';

          if(property_exists($pagination, "previous")){
            $buildDocument .= 'hydra:previous "'.$pagination->previous.'" ;
            ';
          }
          if(property_exists($pagination, "next")){
            $buildDocument .= 'hydra:next "'.$pagination->next.'" ;
            ';
          }

          $buildDocument=substr($buildDocument, 0, -15);
          $buildDocument.=' .';

         echo $buildDocument;
          
    }
    
    function generatePopularJSONLD($prefixes,$pagination){
    
      $i=1;
    
          foreach ($prefixes as $object) {
      
      
             $objNamespace[] = array('@id'=>'_:a'.$i,'pres:prefix' => $object->prefix,'pres:score' => $object->score);
      
          $list[] = array('@id'=>'_:a'.$i);
          $i++;
      }
      $tempArray = array('@id'=>$pagination->current,'@type' => 'hydra:PartialCollectionView','hydra:first' => $pagination->first);
      
      if(property_exists($pagination, "next")){
        $tempArray += [ "hydra:next" => $pagination->next ];
      }
      if(property_exists($pagination, "previous")){
        $tempArray += [ "hydra:previous" => $pagination->previous ];
      }
      $objNamespace[] = $tempArray;
      

      $jsonld['@context']['hydra']='http://www.w3.org/ns/hydra/core#'; 
      $jsonld['@context']['pres']='http://ii.uwb.edu.pl/prefix_resolver#'; 
      
      $jsonld['@graph'] = $objNamespace;
      $list[] = array('@id'=>$pagination->current);
      $jsonld['@graph'][] = array ( '@id' => '_:b','pres:prefixes' => array ('@list' => $list));
      
      
              echo json_encode($jsonld,JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    
    
    
    
    
    }




    if(isset($_GET['page'])){
        $page=$_GET['page']-1;
    }
    else{
        $page=0;
    }
    if(isset($_GET['offset'])){
        $perPage=$_GET['offset'];
    }
    else{
        $perPage=5;
    }

    $printJson='{
        "@context": {
            "name": "http://schema.org/name",
            "description": "http://schema.org/description",
            "image": {
                "@id": "http://schema.org/image",
                "@type": "@id"
            },
            "geo": "http://schema.org/geo",
            "latitude": {
                "@id": "http://schema.org/latitude",
                "@type": "xsd:float"
            },
            "longitude": {
                "@id": "http://schema.org/longitude",
                "@type": "xsd:float"
            },
            "xsd": "http://www.w3.org/2001/XMLSchema#"
        },
            "prefixes": ';

    $response = curl(HOSTNAME.':5984/vocabulary/_design/popular/_view/popular?group=true', 'GET', '', false);

// &limit='.($perPage+1).'&skip='.$perPage*$page

    $php = json_decode($response);

    $rows = $php->rows;


    $pagination = new stdClass;

    $pagination->first=HOSTNAME.'/api/?/popular';
    $pagination->current=HOSTNAME.'/api/?/popular&page='.((int)$page+1);
    if($page>0){
    $pagination->previous =HOSTNAME.'/api/?/popular&page='.($page);
    }
    foreach ($rows as $object) {
        
        $array[$object->key] = $object->value;
        $prefix[] = (object) array('prefix' => $object->key,'score' => $object->value);

    }

    usort($prefix, function($a, $b) { return $b->score - $a->score; });

    $prefix = array_slice($prefix, $perPage*$page, $perPage+1);


    if(count ($prefix)==($perPage+1)){

        $pagination->next = HOSTNAME.'/api/?/popular&page='.($page+2);
        unset($prefix[$perPage]);
    
    }



    // $printJson.=json_encode($prefix,JSON_UNESCAPED_SLASHES);
    // $printJson.=',
    // "pagination": '.json_encode($pagination,JSON_UNESCAPED_SLASHES).'}';

// print_r($prefix);



generatePopular($prefix,$pagination);


















}
);

function whichClicked($prefix,$oauthServer,$userid){


    $receivedHeaders = apache_request_headers();
    // $oauthServer = $receivedHeaders['Server'];

function querytypes($prefix,$oauthServer,$userid,$type){
    $body='{"selector": {
        "prefix": "' . $prefix . '",
        "'.$type.'.' . $oauthServer . '": {"$in": ["' . $userid . '"]}
          
    },
    "fields": ["namespace","'.$type.'.' . $oauthServer . '"],
    "skip": 0}';

    $json = curl(HOSTNAME.':5984/vocabulary/_find', 'POST', $body, false);
    $json_to_php = json_decode($json);
    return $json_to_php;
}


    // echo $body;
    






    $detected = new stdClass;

    $pluses=querytypes($prefix,$oauthServer,$userid,'pluses');
    $minuses=querytypes($prefix,$oauthServer,$userid,'minuses');

    // print_r($pluses);

    if(!empty($pluses->docs)){
        $detected->plus = $pluses->docs[0]->namespace;
    }
    foreach ($minuses->docs as $object) {
        
        if(property_exists($object, 'minuses'))
        if($object->minuses->$oauthServer)
        $detected->minuses[] = $object->namespace;
    }

    // print_r($detected);

    return $detected;
    

}




function getNamespaces($prefix,$limit){
    return curl(HOSTNAME.':5984/vocabulary/_design/prefix/_view/prefix?&startkey=[%22' . $prefix . '%22,%22\ufff0%22]&endkey=[%22' . $prefix . '%22]&descending=true&limit='.$limit, 'GET', '', false);
}



    /**
     * @SWG\Get(
     *     path="/api/{prefix}",
     *     summary="Details of the prefix",
     *     description="Return namespaces of prefix",
     *     operationId="getPrefix",
     *     produces={"application/json+ld"},
     *     @SWG\Parameter(
     *         description="Namespace to return",
     *         in="path",
     *         name="namespace",
     *         required=true,
     *         type="string",
     *         format="string"
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Successful operation",
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Prefix not found"
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */


dispatch_get('/:prefix', function () {


    function generatePrefix($prefixes){

        $headers = apache_request_headers();
    
        if (strpos($headers['Accept'], 'text/turtle') !== false){
            header('Content-Type: text/turtle');
            generatePrefixTurtle($prefixes);}
            else{
                header('Content-Type: application/ld+json');
                generatePrefixJSONLD($prefixes);

            }
    }

    function generatePrefixJSONLD($prefixes){

    
        $i=1;

        foreach ($prefixes as $object) {
    
    
           $objNamespace[] = array('@id'=>'_:a'.$i,'pres:namespace' => $object->key[2],'pres:score' => $object->key[1]);
    
        $list[] = array('@id'=>'_:a'.$i);
        $i++;
    }
    // print_r ($jsonld2);
    
    $jsonld['@context']['pres']='http://ii.uwb.edu.pl/prefix_resolver#';
    $jsonld['@graph'] = $objNamespace;
    $jsonld['@graph'][] = array ( '@id' => '_:b','pres:namespaces' => array ('@list' => $list));
    // $jsonld['@graph'][]['pres:namespaces']['@list']=$repeated2;
    
    
            echo json_encode($jsonld,JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }


    function generatePrefixTurtle($prefixes){
        $buildDocument='@prefix pres: <http://ii.uwb.edu.pl/prefix_resolver#> .
            
        [] <pres:namespaces> (';
    
            foreach ($prefixes as $object) {
            
    
    $buildDocument.='
        [
        <pres:namespace> "'.$object->key[2].'" ;
        <pres:score> '.$object->key[1].' ;
        ]'; 
            }
            $buildDocument.='
        ) .';

         echo $buildDocument;
          
    }



    if(isset($_GET['limit'])){
$limit=$_GET['limit'];
    }
    else{
        $limit=15;
    }


$user=getUserInfo();


    if (property_exists($user, 'id')) {

        $clickedNamespaces = whichClicked(params('prefix'), $user->oauthServer, $user->id);

        // print_r($clickedNamespaces);
        // echo 'test';

        if(isset($clickedNamespaces->plus)){
            header('Link: <'.$clickedNamespaces->plus.'>; data-type="+"',false);
            // echo $clickedNamespaces->plus;
        }
        if(isset($clickedNamespaces->minuses)){
            foreach ($clickedNamespaces->minuses as $object) {
                header('Link: <'.$object.'>; data-type="-"',false);
                // echo $object;        
            }


    }
}



    $json = getNamespaces(params('prefix'),$limit);


    $json_to_php = json_decode($json);

    $count = count ($json_to_php->rows);
    if($count==0){
        halt(404, "Not Found");
    }


    generatePrefix($json_to_php->rows);





}

);


    /**
     * @SWG\Post(
     *     path="/api/{prefix}",
     *     summary="Adding namespace to prefix",
     *     description="Operation allows add new prefix",
     *     operationId="PostNamespace",
     *     produces={"application/json+ld"},
     *     @SWG\Parameter(
 *         name="authorization",
 *         in="header",
 *         description="Press access-token",
 *         required=true,
 *         type="string",
 *     ),
     *     @SWG\Parameter(
     *         description="Namespace",
     *         in="path",
     *         name="namespace",
     *         required=true,
     *         type="string",
     *         format="string"
     *     ),
     *         @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="JSON object containing a plus or minus ranking",
     *         required=true,
     *       @SWG\Schema(
	 *       type="object",
	 *       @SWG\Property(property="@context", type="object",@SWG\Property(property="pres", type="string")),
     * 	     @SWG\Property(property="@id", type="string"),
     *       @SWG\Property(property="pres:ranking", type="string"),
	 *         )
     *     ),
     *     @SWG\Response(
     *         response=201,
     *         description="Successful operation",
     *     ),
     *     @SWG\Response(
     *         response="409",
     *         description="Plus or minus already exists"
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Invalid JSON structure"
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */


dispatch_post('/:prefix', function () {

    $user = getUserInfo();
    if (!property_exists($user, 'id')) {
        halt(401, 'UNAUTHORIZED');
    }

    $obj = file_get_contents("php://input", "r");
    $tresc = json_decode($obj, true);


    // echo substr($tresc['namespace'], -1);
    if (((substr($tresc['namespace'], -1))!='/') && ((substr($tresc['namespace'], -1))!='#')){
        halt(400,'Bad Request');
    }

    $datajson = '{"selector": {
               "namespace": "' . $tresc['namespace'] . '"
        },
        "fields": ["namespace"],
        "limit": 1,
        "skip": 0}';

    $response = curl(HOSTNAME.':5984/vocabulary/_find', 'POST', $datajson, false);
    $responsephp = json_decode($response);


    if (empty($responsephp->docs)) {
        $datajson = '{"prefix": "' . params('prefix') . '",
                "namespace": "' . $tresc['namespace'] . '",
                "pluses": {"Google": [],"github": [],"Reddit": [],"Facebook": []},
                "minuses": {"Google": [],"github": [],"Reddit": [],"Facebook": []},
                "score": 0}';

        curl(HOSTNAME.':5984/vocabulary', 'POST', $datajson, false);
        halt(201, 'Created');
    } 
    else {
        halt(409, 'Conflict');
    }
}
);

function generateDocument($format,$documentString){

    $headers = apache_request_headers();

    if (strpos($headers['Accept'], 'text/turtle') !== false){
        generateTurtle($format,$documentString);
    }

        else{
    header('Content-Type: text/plain; charset=utf-8');
    echo $documentString;
    }

}

function generateTurtle($format,$documentString){


    header('Content-Type: text/turtle');
    echo '@prefix pres: <http://ii.uwb.edu.pl/prefix_resolver#> .
    _:s pres:format "'.$documentString.'"^^pres:'.$format.' .';
}


    /**
     * @SWG\Get(
     *     path="/api/{prefix}/formats/{format}",
     *     summary="Details of the prefix",
     *     description="Return namespaces of prefix",
     *     operationId="getPrefix",
     *     produces={"application/json+ld", "text/turtle"},
     *     @SWG\Parameter(
     *         description="Namespace to return",
     *         in="path",
     *         name="namespace",
     *         required=true,
     *         type="string",
     *         format="string"
     *     ),
     *         @SWG\Parameter(
     *         description="Namespace to return",
     *         in="path",
     *         name="format",
     *         required=true,
     *         type="string",
     *         format="string"
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Successful operation",
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Namespaces of prefixes not found"
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */

dispatch_get('/:prefix/formats/turtle', function () {

    $std = json_decode(getNamespaces(params('prefix'),1));
    if(isset($std->rows[0]))
    generateDocument('turtle','@prefix '.$std->rows[0]->key[0].': <'.$std->rows[0]->key[2].'> .');
    else
    halt(404, 'Not Found');
});

dispatch_get('/:prefix/formats/rdf/xml', function () {

    $std = json_decode(getNamespaces(params('prefix'),1));
    if(isset($std->rows[0]))
    generateDocument('rdf/xml','xmlns:'.$std->rows[0]->key[0].'="'.$std->rows[0]->key[2].'"');
    else
    halt(404, 'Not Found');
});

dispatch_get('/:prefix/formats/rdfa', function () {

    $std = json_decode(getNamespaces(params('prefix'),1));
    if(isset($std->rows[0]))
    generateDocument('rdfa','prefix="'.$std->rows[0]->key[0].': '.$std->rows[0]->key[2].'"');
    else
    halt(404, 'Not Found');
});

dispatch_get('/:prefix/formats/sparql', function () {

    $std = json_decode(getNamespaces(params('prefix'),1));
    if(isset($std->rows[0]))
    generateDocument('sparql','PREFIX '.$std->rows[0]->key[0].': <'.$std->rows[0]->key[2].'>');
    else
    halt(404, 'Not Found');
});

dispatch_get('/:prefix/formats/json-ld', function () {

    $std = json_decode(getNamespaces(params('prefix'),1));
    if(isset($std->rows[0]))
    generateDocument('json-ld','"'.$std->rows[0]->key[0].'": "'.$std->rows[0]->key[2].'"');
    else
    halt(404, 'Not Found');
});

dispatch_get('/:prefix/formats/csv', function () {

    $std = json_decode(getNamespaces(params('prefix'),1));
    if(isset($std->rows[0]))
    generateDocument('csv',$std->rows[0]->key[0].','.$std->rows[0]->key[2]);
    else
    halt(404, 'Not Found');
});

dispatch_get('/:prefix/formats/tsv', function () {

    $std = json_decode(getNamespaces(params('prefix'),1));
    if(isset($std->rows[0]))
    generateDocument('csv',$std->rows[0]->key[0].'	'.$std->rows[0]->key[2]);
    else
    halt(404, 'Not Found');
});
// dispatch_delete('/http:/**', function ()
// {
//     $obj = file_get_contents("php://input", "r");
//     $tresc=json_decode($obj,true);
// curl('localhost:5984/vocabulary/'.$tresc['id'].'?rev='.$tresc['rev'],'DELETE',$datajson,false);

// }
// );

// dispatch_delete('/https:/**', function ()
// {
//     $datajson=search_by('url','https:/'.params(0).'/');
//     curl('localhost:5984/vocabulary/'.$tresc['id'].'?rev='.$tresc['rev'],'DELETE',$datajson);
// }
// );





// dispatch_patch('/:prefix', function () {

//     $user = getUserInfo();
//     if ($user->id == null) {
//         halt(401, 'UNAUTHORIZED');
//     }

//     $hed = apache_request_headers();
//     $server = $hed['Server'];

//     $datajson = '{"selector": {
//     "prefix": "' . params('prefix') . '",
//     "pluses.' . $server . '": {"$in": ["' . $user->id . '"]},
//     "minuses.' . $server . '": {"$in": ["' . $user->id . '"]},
      
// },
// "fields": ["namespace"],
// "limit": 1,
// "skip": 0}';

//     $json = curl('localhost:5984/vocabulary/_find', 'POST', $datajson, false);

// // echo getIdOauth();

//     $json_to_php = json_decode($json);
//     if (isset($json_to_php->docs[0]->url)) {
//         echo '"' . $json_to_php->docs[0]->url . '"';
//     } 
//         else {
//         // halt(404, "NOT FOUND");
//     }
// }

// );

run();

?>