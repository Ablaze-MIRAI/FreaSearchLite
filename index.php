<?php

$FREASEARCH_API = "https://freasearch.org/search?format=json&language=ja-JP&q=";

function HtmlMinify($buffer) {
	$search = ["/\>[^\S ]+/s", "/[^\S ]+\</s", "/(\s)+/s"];
	$replace = [">", "<", "\\1"];
	return preg_replace($search, $replace, $buffer);
}

function  HttpGet(string $url){
    $headers = [
        "accept-language: ja,en;q=0.9,en-GB;q=0.8,en-US;q=0.7",
        "user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/106.0.0.0 Safari/537.36 Edg/106.0.1370.42"
    ];
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_CAINFO => "./cacert.pem",
        CURLOPT_HTTPHEADER => $headers
    ]);
    $result = curl_exec($ch);
    $en = curl_errno($ch);
    curl_close($ch);
    return [$en, $result];
}

function HtmlBuilderTop(){
    return file_get_contents(__DIR__."/templates/top.html");
}

function HtmlBuilderQuery(){
    global $FREASEARCH_API;
    $response = HttpGet($FREASEARCH_API.urlencode($_GET["q"]));
    $result = json_decode($response[1], true);
    if($response[0] !== 0) return "<h1 style=\"color: red\">データ取得エラー({$response[0]})</h1><a href=\".\">トップへ戻る</a>";
    
    $template = file_get_contents(__DIR__."/templates/query.html");
    $proxyignore = false;

    if(isset($_GET["client"]) && !empty($_GET["client"])){
        $template = str_replace("[CHECKED]", "checked", $template);
        $template = str_replace("[CLIENTNOTICE]", "クライアント復号化モード時に再検索する場合はトップからやり直してください", $template);
        $template = str_replace("@media hide", "display: none;", $template);
        $proxyignore = true;
    }

    if(isset($result["unresponsive_engines"])){
        $engines = "";
        foreach($result["unresponsive_engines"] as $value) $engines .= "<li>{$value[0]}({$value[1]})</li>";
        $ERROR = "<h3>WARNING!<h3><small>一部のエンジンから結果を取得できませんでした。結果の精度が低下している可能性があります</small><ul>{$engines}</ul><hr/>";
        $template = str_replace("[ERROR]", $ERROR, $template);
    }

    if(count($result["results"]) !== 0){
        $sitetemplate = file_get_contents(__DIR__."/templates/site.html");
        $sitehtml = "";
        foreach($result["results"] as $value){
            $replaceRule = [
                "[TITLE]" => $value["title"],
                "[URL]" => $value["url"],
                "[CONTENT]" => $value["content"],
                "[ENGINES]" => implode(", ", $value["engines"])    
            ];
            $sitehtml .= str_replace(array_keys($replaceRule), array_values($replaceRule), $sitetemplate);
        }
        $template = str_replace("[SITES]", $sitehtml, $template);
    }else{
        $template = str_replace("[SITES]", "検索結果がありませんでした", $template);
    }
    
    $replaceRule = [
        "[QUERY]" => $_GET["q"],
        "[CHECKED]" => "",
        "[ERROR]" => "",
        "[SITES]" => "",
        "@media hide" => "",
        "[CLIENTNOTICE]" => ""
    ];
    $html = str_replace(array_keys($replaceRule), array_values($replaceRule), $template);
    if($proxyignore){
        $ignoretemplate = file_get_contents(__DIR__."/templates/proxyignore.html");
        $ignoretemplate = str_replace("[BASE64]", base64_encode(HtmlMinify($html)), $ignoretemplate);
        return $ignoretemplate;
    }
    return $html;
}

if(!isset($_GET["q"]) && empty($_GET["q"])){
    echo HtmlMinify(HtmlBuilderTop());
}else{
    echo HtmlMinify(HtmlBuilderQuery());
}