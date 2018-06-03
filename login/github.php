<?php

require '../../vendor/autoload.php';

session_start();

$provider = new League\OAuth2\Client\Provider\Github([
    'clientId'          => '2f1f73b420e57c840193',
    'clientSecret'      => '00b775fd53cd5823d1c748a9a28984be3c55d2f0',
    'redirectUri'       => 'https://prefix.chemskos.com/api/login/github',
]);

if (!isset($_GET['code'])) {

    // If we don't have an authorization code then get one
    $authUrl = $provider->getAuthorizationUrl();
    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: '.$authUrl);
    exit;

// Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {

    unset($_SESSION['oauth2state']);
    exit('Invalid state');

} else {

    // Try to get an access token (using the authorization code grant)
    $token = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
    ]);

    // Optional: Now you have a token you can look up a users profile data
    try {

        // We got an access token, let's now get the user's details
        $user = $provider->getResourceOwner($token);

        // Use these details to create a new profile
        printf('Hello %s!', $user->getNickname());

    } catch (Exception $e) {

        // Failed to get user details
        exit('Oh dear...');
    }

	// Use this to interact with an API on the users behalf
	
	setcookie("access-token", $token->getToken(),time()+3600,'/');
	setcookie("server", 'github',time()+3600,'/');
	header('Location: https://prefix.chemskos.com/');
}

?>