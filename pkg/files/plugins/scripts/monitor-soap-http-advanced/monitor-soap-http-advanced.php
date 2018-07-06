<?php
// variable definitions
$host = "";
$vhost = "";
$port = "80";
$ssl = false;
$url_path = "";
$header = "";
$body = "";
$must_contain = '';
$must_not_contain = '';

// parse up.time environmental variables
if ( strlen(getenv('UPTIME_HOSTNAME')) > 0 ) {
	$host = trim(getenv('UPTIME_HOSTNAME'));
}
if ( strlen(getenv('UPTIME_PORT')) > 0 ) {
	try {
		$port = intval(trim(getenv('UPTIME_PORT')));
	} catch (Exception $ex) {
		// just set it to the default (80)
		$port = 80;
	}
}
if ( strlen(getenv('UPTIME_VIRTUALHOST')) > 0 ) {
	$vhost = trim(getenv('UPTIME_VIRTUALHOST'));
}
if ( strlen(getenv('UPTIME_USE-SSL')) > 0 ) {
	if (getenv('UPTIME_USE-SSL') == "true") {
		$ssl = true;
	}
	else {
		$ssl = false;
	}
}
if ( strlen(getenv('UPTIME_URL_PATH')) > 0 ) {
	$url_path = trim(getenv('UPTIME_URL_PATH'));
}
if ( strlen(getenv('UPTIME_HEADER')) > 0 ) {
	$header = trim(getenv('UPTIME_HEADER'));
}
if ( strlen(getenv('UPTIME_BODY')) > 0 ) {
	$body = trim(getenv('UPTIME_BODY'));
}
if ( strlen(getenv('UPTIME_TEXTMUSTAPPEAR')) > 0 ) {
	$must_contain = trim(getenv('UPTIME_TEXTMUSTAPPEAR'));
}
if ( strlen(getenv('UPTIME_TEXTMUSTNOTAPPEAR')) > 0 ) {
	$must_not_contain = trim(getenv('UPTIME_TEXTMUSTNOTAPPEAR'));
}


// no further changes below
$hostname = $host;
if (!empty($vhost) && strlen($vhost) > 0) {
	$hostname = $vhost;
}
$exit_code = 0;	// 0=OK, 1=WARN, 2=CRIT
$full_url = "http://{$hostname}:{$port}{$url_path}";
//$full_url = "http://{$vhost}:{$port}{$url_path}";

// replace the variables in the body
$body = var_replace($body, '%VIRTUALHOST%', $vhost);
$body = var_replace($body, '%HOSTNAME%', $host);
$body = var_replace($body, '%PORT%', $port);
$body = var_replace($body, '%URL%', $full_url);
// get the updated Content-Length (number of bytes in the body)
$body_length = 0;
if (!empty($body)) {
	$body_length = strlen($body);
}
// replace the variables in the header
$header = var_replace($header, '%VIRTUALHOST%', $vhost);
$header = var_replace($header, '%HOSTNAME%', $host);
$header = var_replace($header, '%PORT%', $port);
$header = var_replace($header, '%URL%', $full_url);
$header = var_replace($header, '%CONTENTLENGTH%', $body_length);

// make sure it has 2 newlines at the end
$header = trim($header) . "\r\n\r\n";

try {
	$raw_response = do_request( $ssl, $hostname, $port, $full_url, $header, $body );
	$raw_response = str_replace("\r\n", "\n", $raw_response);
	$response = trim(substr($raw_response, strpos($raw_response, "\n\n", 0)));
	
	$msg = "OK - Monitor ran successfully";
	
	// check if the must_contain and must_not_contain strings exist/not exist
	if (strlen($must_contain)) {
		if (preg_match("/{$must_contain}/i", $response)) {	// must contain
			if (strlen($must_not_contain)) {
				if (preg_match("/{$must_not_contain}/i", $response)) {	// must NOT contain
					// error
					$exit_code = 2;
					$msg = "Error - Response contained '{$must_not_contain}'";
				}
				else {
					// all good
					$exit_code = 0;
				}
			}
		}
		else {
			// doesn't contain
			$exit_code = 2;
			$msg = "Error - Response did not contain '{$must_contain}'";
		}
	}
	else if (strlen($must_not_contain)) {
		if (preg_match("/{$must_not_contain}/i", $response)) {	// must NOT contain
			// error
			$exit_code = 2;
			$msg = "Error - Response contained '{$must_not_contain}'";
		}
	}
	
	print "message {$msg}\n";
	print "response_code " . get_response_code($raw_response) . "\n";
	print "response_message " . get_response_message($raw_response) . "\n";
	// handle newlines breaking up the output
	$response = str_replace("\n", "\nHTTP-Output ", $response);
	print "HTTP-Output {$response}";
	
} catch (Exception $ex) {
	print_r($ex);
	$exit_code = 2;
}





exit($exit_code);

function do_request( $ssl, $host, $port, $url, $header, $body, $timeout = 45 ) {
	$response = '';
	$errorno = 0;
	$errorstr = '';

	//try {
		if ($ssl) {
//			$context = stream_context_create();
//			$result = stream_context_set_option($context, 'ssl', 'local_cert', '/etc/ssl/certs/cacert.pem');

$contextOptions = array(
    'ssl' => array(
        'verify_peer' => true, // You could skip all of the trouble by changing this to false, but it's WAY uncool for security reasons.
        'cafile' => '/etc/ssl/certs/cacert.pem',
        'CN_match' => 'example.com', // Change this to your certificates Common Name (or just comment this line out if not needed)
        'ciphers' => 'HIGH:!SSLv2:!SSLv3',
        'disable_compression' => true,
    )
);

$context = stream_context_create($contextOptions);

$resource = stream_socket_client("tcp://{$host}:{$port}", $errorno, $errorstr, $timeout, STREAM_CLIENT_CONNECT, $context);


//			$resource = fsockopen('ssl://' . $host, $port, $errorno, $errorstr, $timeout);
		} else {
			$resource = fsockopen($host, $port, $errorno, $errorstr, $timeout);
		}
		//Attempt to establish a connection to agent on port 80. On error, place the error number into $errorno, and a string response to $errorstr. Timeout after 10 seconds.
		if (!$resource) {
			//fsockopen failed
			echo "No connection established. Error: " . $errorstr . "[" . $errorno . "]\n";
		} else {
			// successfully opened a socket
			// now let's write the post data (header + body)
			fwrite($resource, $header . $body);
			//while there is data to read from $resource…
			while (!feof($resource)) {
				//read the data, 2048 bytes at a time and echo it out to stdout
				$response .= fgets($resource, 1024);
			}
			//no more data to read, close the resource
			fclose($resource);
		}
	/*} catch (Exception $e) {
		print "Error:";
		var_dump($e->getMessage());
	}*/
	return $response;
}

function get_response_code($response) {
	$rv = $response;
	// check if the string is not empty and that it has a newline
	if (!empty($response) && strlen($response) > 0 && strpos($response, "\n", 0) > 0) {
		// first, let's get the status line (first line)
		$status_line = substr($response, 0, strpos($response, "\n", 0));
		$arr = preg_split("/ /", $status_line, 3);
		
		if (!empty($arr[1])) {
			$rv = $arr[1];
		}
	}
	return $rv;
}
function get_response_message($response) {
	$rv = $response;
	// check if the string is not empty and that it has a newline
	if (!empty($response) && strlen($response) > 0 && strpos($response, "\n", 0) > 0) {
		// first, let's get the status line (first line)
		$status_line = substr($response, 0, strpos($response, "\n", 0));
		$arr = preg_split("/ /", $status_line, 3);
		if (!empty($arr[2])) {
			$rv = $arr[2];
		}
	}
	return $rv;
}

function var_replace($haystack, $needle, $replace) {
	if (!empty($replace) && strlen($replace) > 0) {
		$haystack = str_replace($needle, $replace, $haystack);
	}
	return $haystack;
}

?>
