<?php
include 'config.php';

if (!isset($_SERVER["HTTP_X_HUB_SIGNATURE"])) {
    die('missing server var');
}

list($algo, $hash) = explode('=', $_SERVER["HTTP_X_HUB_SIGNATURE"], 2);

$payload = file_get_contents('php://input');

$payloadHash = hash_hmac($algo, $payload, $secret);

if ($hash !== $payloadHash) {
    http_response_code(401);
    echo "Bad secret";
    exit;
}

$data = json_decode($payload, true);

echo "Authenticated properly\nDelivery ID: ".$_SERVER["HTTP_X_GITHUB_DELIVERY"]."\nRepository to deploy: ".$data["repository"]["full_name"]."\n";

echo passthru("/bin/bash ".__DIR__."/pullsimple.sh ".$data["repository"]["name"]." ".$data["repository"]["full_name"]." ".$auth." " .$data["repository"]["clone_url"]." 2>&1");

if (file_exists('/var/www/'.$data["repository"]["name"].'/post-deploy-hook.php')) {
    include('/var/www/'.$data["repository"]["name"].'/post-deploy-hook.php');
}

if (file_exists('/var/www/'.$data["repository"]["name"].'/post-deploy-hook.sh')) {
    echo passthru('/bin/bash /var/www/'.$data["repository"]["name"].'/post-deploy-hook.sh 2>&1');
}

if (isset($email_from, $email_to)) {
    mail($email_to, "[".$data["repository"]["full_name"]."] New ".$_SERVER["HTTP_X_GITHUB_EVENT"]." triggered a deployment", ob_get_contents(), "From: ".$email_from);
}

if ($printoutput) {
    file_put_contents('index.html', '<pre>'.ob_get_contents().'</pre>');
}
