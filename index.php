<?PHP 
/*
To integrate Twitter OAuth 2.0 in PHP and access direct messages (DMs), you'll need to follow these steps:
Authenticate with Twitter:
Conceal the code value within a hidden input field
*/

// @post method 
function twitterOAuth(Request $request){
    if($request->code) {
        // #2 get access token by the authorization code  
        $this->getAccessToken($request->code);
    }else{
        // #1 if there's no authorization code
        return $this->getAuthorizationCode();
    }
}

 
//getAuthorizationCode
// Please ensure you've thoroughly reviewed your complete callback_uri within your Twitter developer portal
function getAuthorizationCode() {
    $authorize_url = "https://twitter.com/i/oauth2/authorize";
    $callback_uri =config('twitter.callback_uri'); //GET method
    $client_id = config('twitter.client_id');
    $client_secret = config('twitter.client_secret');
    $str = (pack('H*', hash("sha256", config('twitter.code_challenge'))));
    $code_challenge = rtrim(strtr(base64_encode($str), '+/', '-_'), '=');

    $authorization_redirect_url = $authorize_url . "?response_type=code&client_id=" . $client_id . "&redirect_uri=" . $callback_uri . "&scope=tweet.read%20users.read%20follows.read%20dm.read%20dm.write%20offline.access&state=state&code_challenge=".$code_challenge."&code_challenge_method=plain";
    return redirect()->to($authorization_redirect_url);
}

// getAccessToken
function getAccessToken($authorization_code) {
    $token_url = "https://api.twitter.com/2/oauth2/token";
    $callback_uri =config('twitter.callback_uri');
    $client_id = config('twitter.client_id');
    $client_secret = config('twitter.client_secret');
    $str = (pack('H*', hash("sha256", config('twitter.code_challenge'))));
    $code_verifier = rtrim(strtr(base64_encode($str), '+/', '-_'), '=');


    $authorization = base64_encode("$client_id:$client_secret");
    $header = array("Authorization: Basic {$authorization}","Content-Type: application/x-www-form-urlencoded");
    $content = "grant_type=authorization_code&code=$authorization_code&client_id=$client_id&code_verifier=$code_verifier&redirect_uri=$callback_uri";

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $token_url,
        CURLOPT_HTTPHEADER => $header,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $content
    ));
    $response = curl_exec($curl);
    curl_close($curl);

    if ($response === false) {
        //save error  and redirect
        $twitterError = curl_error($curl);;
        return redirect()->route('mailboxes.twitter.settings');

    } elseif (isset(json_decode($response)->error)) {
        //save error  and redirect
        $twitterError = $response;
        return redirect()->route('mailboxes.twitter.settings');
    }
    else{
        //save access_token & refresh_token
        $TwitterAccessToken = json_decode($response)->access_token;
        $TwitterRefreshToken = json_decode($response)->refresh_token;
        return redirect()->route('mailboxes.twitter.settings');
     }
}


//Get Direct Messages (DMs):
//Use the access_token to gain access to protected Direct Messages
function getDms(){
    $dm_events_url= "https://api.twitter.com/2/dm_events?dm_event.fields=id,text,event_type,dm_conversation_id,created_at,sender_id&user.fields=created_at,description,id,location,name";
    $header = array("Authorization: Bearer {$TwitterAccessToken}");

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $dm_events_url,
        CURLOPT_HTTPHEADER => $header,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_RETURNTRANSFER => true
    ));
    $response = curl_exec($curl);
    curl_close($curl);

    return json_decode($response, true);
}