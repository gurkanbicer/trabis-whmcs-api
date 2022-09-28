<?php 
//Netdirekt-Trabis WHMCS API v0.1

use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;

require_once 'vendor/autoload.php';

define('api_key', ''); // https://trabis.netdirekt.com.tr panelinizden key alınız.
define('customfield_kurumsal', 'customfields5'); // WHMCS'e göre düzenleyiniz.
define('customfield_vergidairesi', 'customfields2'); // WHMCS'e göre düzenleyiniz.
define('customfield_vergino', 'customfields3'); // WHMCS'e göre düzenleyiniz.
define('customfield_tcno', 'customfields4'); // WHMCS'e göre düzenleyiniz.
define('dns_ns1', 'izm.netdirekt.com.tr'); // Firmanıza göre düzenleyiniz.
define('dns_ns2', 'ist.netdirekt.com.tr'); // Firmanıza göre düzenleyiniz.
define('dns_ns3', 'frk.netdirekt.com.tr'); // Firmanıza göre düzenleyiniz.
define('dns_ns4', 'ams.netdirekt.com.tr'); // Firmanıza göre düzenleyiniz.
define('dns_ns5', null);

// Şehir alanı Türkçe karakter içerebildiğinden strtoupper fonksiyonunda sorun yaşanıyor.
// Bu nedenle düzeltme için city_like_helper fonksiyonu eklendi.
function city_like_helper($str) {
    return strtoupper(str_ireplace(
        ["Ç","İ","I","Ğ","Ö","Ş","Ü","ç","i","ı","ğ","ö","ş","ü"], 
        ["C","I","I","G","O","S","U","C","I","I","G","O","S","U"], 
        $str
    ));
}

// Trabisin telefon formatı 90-555-444-3322 şeklinde ve telefon formatları çok çeşitli.
// Bu nedenle önce RFC 3966 standartına, bu formattan da Trabise uygun hale getirildi.
function phone_format_helper($phone, $countryCode) {
    $phoneNumberUtil = PhoneNumberUtil::getInstance();
    $phoneNumberObject = $phoneNumberUtil->parse($phone, $countryCode);
    $phoneRFC3966 = $phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::RFC3966);

    $phoneClean = str_ireplace('tel:+', '', $phoneRFC3966);
    $phoneCleanExp = explode('-', $phoneClean);

    $newPhoneFormat = $phoneCleanExp[0] . '-' . $phoneCleanExp[1] . '-' . $phoneCleanExp[2] . '-';

    unset($phoneCleanExp[0]);
    unset($phoneCleanExp[1]);
    unset($phoneCleanExp[2]);

    $newPhoneFormat .= implode('', $phoneCleanExp);
    return $newPhoneFormat;
}

function trabis_RegisterDomain($params) {
    $phone = phone_format_helper($params['phonenumber'], $params['country']);
    
    /*$phoneLength = strlen($phone);
    if($phoneLength == '11' && substr($phone,0,1) == '0'){
        $phone = '9'.$phone;
    }elseif($phoneLength == '13' && substr($phone,0,2) == '+9'){
        $phone = substr($phone, 1);
    }
    $fixedPhone = substr($phone,0,2).'-'.substr($phone,2,3).'-'.substr($phone,5,7);*/
    
    $nameserver1 = $params["ns1"];
    $nameserver2 = $params["ns2"];
    $nameserver3 = $params["ns3"];
    $nameserver4 = $params["ns4"];
    $nameserver5 = $params["ns5"];

    if(($nameserver1 == "" || $nameserver2 == "")) {      
        $nameserver1 = dns_ns1;
        $nameserver2 = dns_ns2;
        $nameserver3 = dns_ns3;
        $nameserver4 = dns_ns4;
        $nameserver5 = dns_ns5;
    }

    $post = array(
        'api_key' => api_key,
        'domain' => $params['domainname'],
        'duration' => $params["regperiod"],
        'email' => $params["email"],
        'countryId' => '215', //Türkiye
        'address1' => $params["address1"],
        'address2' => $params["address2"],
        //'phone' => $fixedPhone,
        'phone' => $phone,
        'fax' => null,
        'zipCode' => $params["postcode"],
        'nsName' => array(
            '0' => $nameserver1,
            '1' => $nameserver2,
            '2' => $nameserver3,
            '3' => $nameserver4,
            '4' => $nameserver5
        )
    );

    
    //$hizmet_turu = $params['customfields5'];
    $hizmet_turu = ($params[customfield_kurumsal] == "on") ? "Kurumsal" : "Bireysel"; 
    if($hizmet_turu == 'Bireysel'){
        $type = '1';
        $post['name'] = "{$params["firstname"]} {$params["lastname"]}";
        //$post['citizenId'] = $params["customfields4"];
        $post['citizenId'] = $params[customfield_tcno];
    } elseif ($hizmet_turu == 'Kurumsal') {
        $type = '2';
        $client = new WHMCS\Client($params["userid"]);
        $details = $client->getDetails($contactid);
        $organization = $details['companyname'];
        $post['organization'] = $organization;
        //$post['taxOffice'] = $params["customfields2"];
        //$post['taxNumber'] = $params["customfields3"];
        $post['taxOffice'] = $params[customfield_vergidairesi];
        $post['taxNumber'] = $params[customfield_vergino];
    }
    
    $post['type'] = $type;

    $get_city = [
        'api_key' => api_key
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://trabis.netdirekt.com.tr/api/get_cities");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $get_city);
    $result = curl_exec($ch);
    curl_close($ch);
    $cities = json_decode($result, true);
    
    foreach($cities['cities'] as $city){
        //if($params['city'] == $city['like']){
        if(city_like_helper($params['city']) == $city['like']){
            $cityId = $city['id'];
        }
    }
    
    $post['cityId'] = $cityId;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://trabis.netdirekt.com.tr/api/register_domain");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    $result = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($result, true);

    if($json["msg"] == "NEEDS_MANUAL_REGISTRATION_WITH_DOCUMENTS"){
        $array = array(
            'date' => date('Y-m-d'),
            'title' => ".TR (belgeli) alan adı siparişi gelmiştir.",
            'description' => "" .$params['domainname'] . " alan adı için lütfen gerekli belgeler ile trabis panel üzerinden başvuru gerçekleştiriniz.",
            'admin' => '',
            'status' => 'Pending',
            'duedate' => date('Y-m-d', mktime(date('h'), date('i'), date('s'), date('m'), date('d'), date('Y')))
        );
        insert_query('tbltodolist', $array);
    }

    $array = array(
        'date' => date('Y-m-d'),
        'title' => ".TR Alan adı otomatik kayıt edilemedi",
        'description' => "" .$params['domainname'] . " alan adı kaydı sırasında ".$json["msg"]." hatası alınmıştır. Lütfen hata mesajına göre gerekli aksiyonu alınız.",
        'admin' => '',
        'status' => 'Pending',
        'duedate' => date('Y-m-d', mktime(date('h'), date('i'), date('s'), date('m'), date('d'), date('Y')))
    );
    if($json["status"] == '1'){
        return success;
    } else if ($json["status"] == "0"){
        insert_query('tbltodolist', $array);
        return array("error" => $json["msg"]);
    } else {
        insert_query('tbltodolist', $array);
        return array("error" => "error");
    }

}

 function trabis_ReNewDomain($params) {
    $post = array(
        'api_key' => api_key,
        'domain' => $params['domainname'],
        'duration' => $params["regperiod"]
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://trabis.netdirekt.com.tr/api/renew_domain");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    $result = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($result, true);

    $array = array(
        'date' => date('Y-m-d'),
        'title' => ".TR Alan adı otomatik kayıt edilemedi",
        'description' => "" .$params['domainname'] . " alan adı yenilemesinde".$json["msg"]." hatası alınmıştır. Lütfen hata mesajına göre gerekli aksiyonu alınız. Lütfen alan adını yenilemekte acele etmeyiniz. Expire date'i doğru bir şekilde sorgulayıp, yenilenmediyse işlemlere devam ediniz.",
        'admin' => '',
        'status' => 'Pending',
        'duedate' => date('Y-m-d', mktime(date('h'), date('i'), date('s'), date('m'), date('d'), date('Y')))
    );
    if($json["status"] == '1'){
        return success;
    } else if ($json["status"] == "0"){
      
        insert_query('tbltodolist', $array);
        return array("error" => $json["msg"]);
    } else {
        insert_query('tbltodolist', $array);
        return array("error" => "error");
    }
}

function trabis_GetNameservers($params, $detail = null)
{
    $post = [
        'api_key' => api_key,
        'domain' => $params["domainname"]
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://trabis.netdirekt.com.tr/api/get_ns");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    $result = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($result, true);

    $values = array();
    foreach($json["nameservers"] as $key => $nameServer)
    {
        $key++;
        //if($nameServer["nsType"] == "other")
        //{
            $values["ns" . $key] = $nameServer["nsName"];
        //}
    }
    if($detail == '1'){
        return $json;
    } else {
        return $values;
    }
}

function trabis_SaveNameservers($params)
{
    $nameserver1 = $params["ns1"];
    if($nameserver1 == "") $nameserver1 = "empty";
    $nameserver2 = $params["ns2"];
    if($nameserver2 == "") $nameserver2 = "empty";
    $nameserver3 = $params["ns3"];
    if($nameserver3 == "") $nameserver3 = "empty";
    $nameserver4 = $params["ns4"];
    if($nameserver4 == "") $nameserver4 = "empty";
    $nameserver5 = $params["ns5"];
    if($nameserver5 == "") $nameserver5 = "empty";

    $post = array(
        'api_key' => api_key,
        'domain' => $params["domainname"],
        'nameservers' => array(
            'ns1' => $nameserver1,
            'ns2' => $nameserver2,
            'ns3' => $nameserver3,
            'ns4' => $nameserver4,
            'ns5' => $nameserver5
        )
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://trabis.netdirekt.com.tr/api/modify_ns");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    $result = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($result, true);

    if($json["status"] == "0") return array("error" => $json["msg"]);

    return "success";
}

function trabis_RegisterNameserver($params)
{
    $post = array(
        'api_key' => api_key,
        'domain' => $params["domainname"],
        'ns' => '-1',
        'name' => $params["nameserver"],
        'ip' => $params["ipaddress"]
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://trabis.netdirekt.com.tr/api/add_child_ns");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    $result = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($result, true);
    
    if($json["status"] == "0") return array("error" => $json["msg"]);

    return "success";
}

function trabis_DeleteNameserver($params)
{
    $post = array(
        'api_key' => api_key,
        'domain' => $params["domainname"],
        'name' => $params["nameserver"],
        'default_ns' => array(
            array("nsName" => dns_ns1, "nsIP" => null),
            array("nsName" => dns_ns2, "nsIP" => null)
        ),
    );
    
    if (!is_null(dns_ns3)) {
        $post['default_ns'][] = array("nsName" => dns_ns3, "nsIP" => null);
    }
    
    if (!is_null(dns_ns4)) {
        $post['default_ns'][] = array("nsName" => dns_ns4, "nsIP" => null);
    }
    
    if (!is_null(dns_ns5)) {
        $post['default_ns'][] = array("nsName" => dns_ns5, "nsIP" => null);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://trabis.netdirekt.com.tr/api/rm_child_ns_v2");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    $result = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($result, true);

    if($json["status"] == '1'){
        return success;
    } else if ($json["status"] == "0"){
        return array("error" => $json["msg"]);
    } else {
        return array("error" => "error");
    }
}
?>
