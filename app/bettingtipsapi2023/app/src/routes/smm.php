<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use LanguageDetector\LanguageDetectorBuilder;


$app = new \Slim\App;
   



$app->add(function ($req, $res, $next) {
    $response = $next($req, $res);
    return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

$app->get('/hello', function ($request, $response, $args) {
    $response->getBody()->write("Hello, World! BETTİNG TİPS 2023");
    return $response;
});



// Define a route for testing the database connection
$app->get('/test-db-connection', function ($request, $response) {
    try {
        $db = new Db();
        $connection = $db->connect();
        $status = $connection ? 'Connected to the database successfully' : 'Failed to connect to the database';
        
        return $response->withJson(['status' => $status]);
    } catch (PDOException $e) {
        return $response->withJson(['error' => 'Database connection error: ' . $e->getMessage()], 500);
    }
});


//User Control
function createOrUpdateUser($user_deviceid, $device_os) {
    $db = new Db();
    $conn = $db->connect();

    // Önce veritabanında aynı device id ile kullanıcı var mı kontrol edelim
    $checkQuery = "SELECT user_id, device_os FROM bettingtips2023_users WHERE user_deviceid = :deviceid";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bindParam(':deviceid', $user_deviceid);
    $stmt->execute();

    $userExists = $stmt->fetch();

    if (!$userExists) {
        // Kullanıcı yoksa yeni kayıt oluştur
        $insertQuery = "INSERT INTO bettingtips2023_users (user_deviceid, device_os) VALUES (:deviceid, :device_os)";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bindParam(':deviceid', $user_deviceid);
        $stmt->bindParam(':device_os', $device_os);
        $stmt->execute();
        return "Yeni kullanıcı kaydı oluşturuldu.";
    } else {
        // Kullanıcı varsa ve device_os boşsa veya belirtilmediyse, device_os'u "android" olarak güncelle
        if (empty($userExists['device_os'])) {
            $updateQuery = "UPDATE bettingtips2023_users SET device_os = 'android' WHERE user_deviceid = :deviceid";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bindParam(':deviceid', $user_deviceid);
            $stmt->execute();
            return "Kullanıcı bulundu, device_os güncellendi.";
        }
        return "Bu cihaz zaten bir kullanıcıya ait.";
    }
}


$app->post('/create-user', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $user_deviceid = $data['user_deviceid'];
    $device_os = $data['device_os'] ?? 'android';

    $result = createOrUpdateUser($user_deviceid,$device_os);

    return $response->withJson(['message' => $result]);
});

function getDeviceOSByDeviceID($user_deviceid) {
    try {
        $db = new Db();
        $connection = $db->connect();

        $query = "SELECT device_os FROM bettingtips2023_users WHERE user_deviceid = :user_deviceid";
        $statement = $connection->prepare($query);
        $statement->bindParam(':user_deviceid', $user_deviceid);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['device_os'] !== null) {
            return $result['device_os'];
        } else {
            return "android";
        }
    } catch (PDOException $e) {
        // Hata oluştuğunda gerekli işlemler burada yapılabilir
        // Örneğin: hata mesajını loglamak veya uygun bir şekilde raporlamak
        // Bu örnek sadece varsayılan "android" değerini döndürüyor.
        return "android";
    }
}


$app->get('/livescores/list/{app_user_id}', function (Request $request, Response $response, $args) {
    // app_user_id'yi al
    $appUserId = $args['app_user_id'];

    // API request setup
    $url = 'https://playprotips.com/api/live-scores';
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Email: admin@admin.com',
        'API-Key: 8e5417c9-a630-4922-aff3-6c611e54ca9c'
    ]);

    // Perform the API request
    $jsonResponse = curl_exec($curl);
    curl_close($curl);

    // Return the raw JSON response
    return $jsonResponse;

    // Veriyi JSON olarak çöz
    $jsonData = json_decode($jsonResponse, true);

    // JSON verisini düzenleyerek döndür
    $output = array_values($jsonData['football']);

    return $response->withJson(['football' => $output]);
});





//Bet List
$app->get('/bet/list/{app_user_id}', function (Request $request, Response $response, $args) {
    $url = 'https://playprotips.com/api/betting-tips';
    $appUserId = $args['app_user_id'];

    $device_os = getDeviceOSByDeviceID($appUserId);
    $is_subscription_active = isSubscriptionActive($appUserId, $device_os);

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Email: admin@admin.com',
        'API-Key: 8e5417c9-a630-4922-aff3-6c611e54ca9c'
    ]);

    $jsonResponse = curl_exec($curl);

    if ($jsonResponse === false) {
        return $response->withJson(['error' => curl_error($curl)], 500);
    }

    curl_close($curl);

    $data = json_decode($jsonResponse, true);

    $match_count = 0;

    foreach ($data['football'] as $key => &$match) {
        

        if ($match['result'] === '2') {
            $match_count++;
            if ($match_count > 1) { // Sadece bir maç null olacak
                unset($data['football'][$key]);
                continue;
            }
        }
    }

    if (!$is_subscription_active) {
        foreach ($data['football'] as &$match) {
            if (!isset($match['result']) || $match['result'] === '') {
                $match['result'] = '0';
            }

            if ($match['result'] === '0') {
                $match['prediction']['type'] = 'XXX';
                $match['prediction']['name'] = 'XXX';
            }
        }
    }

    $response->getBody()->write(json_encode($data, true));

    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

// Verileri çekmek için bir GET endpoint'i tanımla
$app->get('/bettingtips-ads', function (Request $request, Response $response) {
    try {
        // Veritabanı bağlantısını oluştur
        $db = new Db();
        $conn = $db->connect();
        
        // SQL sorgusu
        $sql = "SELECT * FROM bettingtips_ads WHERE is_show = 1";
        
        // Sorguyu hazırla
        $stmt = $conn->prepare($sql);
        
        // Sorguyu çalıştır
        $stmt->execute();
        
        // Sonuçları al
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // JSON olarak yanıt ver
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (PDOException $e) {
        // Hata durumunda hata mesajını JSON olarak yanıtla
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});



//RevenueCat 

$app->get('/getOfferings/{app_user_id}', function (Request $request, Response $response, $args) {
    $appUserId = $args['app_user_id'];
    $apiResponse = get_user_offerings($appUserId);

    $response->getBody()->write($apiResponse);

    return $response->withHeader('Content-Type', 'application/json');
});

function get_user_offerings($appUserId) {
    $url = "https://api.revenuecat.com/v1/subscribers/{$appUserId}/offerings";
    $headers = array(
        'X-Platform: android',
        'accept: application/json',
        'authorization: Bearer goog_RuFVnaepKuQONLidzJonWMiBXWy'
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if(curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
    }

    curl_close($ch);

    return $response;
}

$app->get('/isActive/{app_user_id}', function (Request $request, Response $response, $args) {
    $appUserId = $args['app_user_id'];
    $apiResponse = isSubscriptionActive($appUserId);
    
    if($apiResponse){
        return 'ABONELİK AKTİFTİR';
    }else{
        return 'ABONELİK YOKTUR VEYA SÜRESİ DOLMUŞTUR!';
    }

    $response->getBody()->write($apiResponse);

    return $response->withHeader('Content-Type', 'application/json');
});

function isSubscriptionActive($appUserId,$device_os) {
    $apiResponse = get_user($appUserId,$device_os); // Burada get_user fonksiyonunuzu çağırıyorum. Bu fonksiyonun API çağrısını yaptığınızı varsayıyorum.
    $responseData = json_decode($apiResponse, true);
   
    
    

    if (isset($responseData['subscriber']['entitlements']['vip_bettingtips_2023'])) {
        $subscription = $responseData['subscriber']['entitlements']['vip_bettingtips_2023'];

        $currentDateTime = new DateTime();
        $subscriptionExpiresDateTime = new DateTime($subscription['expires_date']);

        if ($subscriptionExpiresDateTime > $currentDateTime) {
            return true; // Abonelik hala aktif
        }
    }

    return false; // Abonelik süresi dolmuş veya abonelik bulunamamış
}



function get_user($appUserId,$device_os) {
    
    $revenuecat_api_key = 'goog_RuFVnaepKuQONLidzJonWMiBXWy'; //android api key
    
    if($device_os === 'ios'){
        $revenuecat_api_key = 'appl_bCoiDkgnacsbnjGhNOzeIeVGAFD'; //ios api key
    }else{
        $revenuecat_api_key = 'goog_RuFVnaepKuQONLidzJonWMiBXWy'; //android api key
    }
    
    $url = "https://api.revenuecat.com/v1/subscribers/{$appUserId}";
    $headers = array(
        'X-Platform: android',
        'accept: application/json',
        'authorization: Bearer ' . $revenuecat_api_key
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if(curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
    }

    curl_close($ch);

    return $response;
}

$app->get('/getSubscriberInfo/{app_user_id}', function (Request $request, Response $response, $args) {
    $appUserId = $args['app_user_id'];
    $apiResponse = get_user($appUserId);

    $response->getBody()->write($apiResponse);

    return $response->withHeader('Content-Type', 'application/json');
});

function checkSubscriptionStatus($appUserID, $apiKey) {
    $url = "https://api.revenuecat.com/v1/subscribers/$appUserID";

    $headers = array(
        "Authorization: Basic " . $apiKey
    );

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);

    if ($response === false) {
        return false; // Curl request failed
    }

    curl_close($ch);

    $data = json_decode($response, true);
    return $data;

    if (isset($data['subscriber']['entitlements']['pro']) && $data['subscriber']['entitlements']['pro']['expires_date'] !== null) {
        $expiresTimestamp = $data['subscriber']['entitlements']['pro']['expires_date'] / 1000;
        $expiresDate = date("Y-m-d H:i:s", $expiresTimestamp);
        $isActive = true;
    } else {
        $expiresDate = null;
        $isActive = false;
    }

    $subscriptionInfo = array(
        'isActive' => $isActive,
        'expiresDate' => $expiresDate,
        'rawResponse' => $data
    );

    return $subscriptionInfo;
}

$app->get('/checkSubscription/{appUserID}', function (Request $request, Response $response, array $args) {
    $appUserID = $args['appUserID'];
    $apiKey = 'goog_RuFVnaepKuQONLidzJonWMiBXWy'; // Your RevenueCat API key

    $subscriptionInfo = checkSubscriptionStatus($appUserID, $apiKey);
    return $subscriptionInfo;

    if ($subscriptionInfo === false) {
        return $response->withJson(array('error' => 'Error occurred while checking subscription.'), 500);
    } else {
        if ($subscriptionInfo['isActive']) {
            return $response->withJson(array('status' => 'active', 'expiresDate' => $subscriptionInfo['expiresDate']), 200);
        } else {
            return $response->withJson(array('status' => 'inactive'), 200);
        }
    }
});




/*User Transactions 

function getUserTransactions($user_deviceid) {
    try {
        $db = new Db();
        $conn = $db->connect();
        
        $query = "SELECT * FROM magicai_transactions WHERE user_deviceid = :user_deviceid";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_deviceid', $user_deviceid, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $conn = null;
        
        return $result;
    } catch (PDOException $e) {
        // Handle database errors here
        return array('error' => $e->getMessage());
    }
}

function getTotalTransactionsThisMonth($user_deviceid) {
    $db = new Db();
    $conn = $db->connect();
    
    $currentYear = date('Y');
    $currentMonth = date('m');
    
    $query = "SELECT COUNT(*) AS transaction_count FROM magicai_transactions WHERE user_deviceid = :deviceid AND YEAR(date) = :year AND MONTH(date) = :month";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':deviceid', $user_deviceid);
    $stmt->bindParam(':year', $currentYear);
    $stmt->bindParam(':month', $currentMonth);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['transaction_count'];
}

$app->get('/userTransactions/deviceid/{user_deviceid}', function ($request, $response, $args) {
    $user_deviceid = $args['user_deviceid'];
    
    $transactions = getUserTransactions($user_deviceid);
    
    if (isset($transactions['error'])) {
        return $response->withJson(array('error' => $transactions['error']), 500);
    }
    
    $thisMonthTransactionCount = getTotalTransactionsThisMonth($user_deviceid);
    $totalTransactionCount = count($transactions);
    
    $result = array(
        'user_transactions' => $transactions,
        'this_month_transaction_count' => $thisMonthTransactionCount,
        'total_transaction_count' => $totalTransactionCount
    );
    
    return $response->withJson($result);
});
*/


 
