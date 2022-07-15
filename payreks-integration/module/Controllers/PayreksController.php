<?php

namespace PayreksIntegration\Controllers;

use App\Controllers\Controller;
use App\Models\Setting;
use App\Models\StorePackage;
use App\Models\StorePayment;
use App\Models\User;
use App\Store;

use Slim\Routing\RouteContext;

class PayreksController extends Controller
{

    public function logfile($fn, $str, $add_date = true)
    {
        $have_file = @file_exists($fn);
        $fout = @fopen($fn, "a");
        if (!$fout) return;
        if (flock($fout, LOCK_EX)) {
            if ($add_date)
                $log_str = date("[Y-m-d, H:i:s]") . " " . $str . "\n";
            else
                $log_str = $str . "\n";
            fwrite($fout, $log_str);
            fflush($fout);
            flock($fout, LOCK_UN);
        }
        fclose($fout);
        if (!$have_file) @chmod($fn, 0664);
    }

    public function session($request, $response, $args)
    {

        $settings = Setting::getByCategory('store');
        $params = $request->getParams();
        $total = $request->getAttribute('total');
        $posData = [
            'steamid' => $this->auth->user()->steamid,
            'name' => $this->auth->user()->name,
            'type' => $params['type'],
            'total' => $total
        ];

        if ($params['type'] == 'credits') {
            $posData['quantity'] = $params['quantity'];
        } else if ($params['type'] == 'package') {
            $package = $request->getAttribute('package');
            $posData['quantity'] = 1;
            $posData['package_id'] = $package->id;
        }

        $inputOptions = [];

        if ($settings['payreks_payment_1']) {
            $inputOptions[1] = "Kredi Kartı";
        }
        if ($settings['payreks_payment_2']) {
            $inputOptions[2] = "Banka Transferi";
        }
        if ($settings['payreks_payment_3']) {
            $inputOptions[3] = "Mobil Ödeme";
        }
        if ($settings['payreks_payment_4']) {
            $inputOptions[4] = "İninal Ödeme";
        }

        return $response->withJSON([
            'success' => !isset($result['error']) ? true : false,
            'message' => isset($result['error']) ? $result['error'] : null,
            'inputOptions' => json_encode($inputOptions)
        ]);
    }

    public function callback($request, $response, $args)
    {
        $settings = Setting::getByCategory('store');
        $params = $request->getParams();

        function payreksFilter($string)
        {
            $escapes = ["--", ";", "/*", "*/", "//", "#", "||", "<", "|", "&", ">", "'", ")", "(", "*", "\""];
            $filterString = $string;
            foreach ($escapes as $row)
                $filterString = str_replace($row, "", $filterString);
            return $filterString;
        }

        //Payreks Hash Control Function
        function payreksHashControl($type, $data, $key)
        {
            $context = hash_init($type, HASH_HMAC, $key);
            hash_update($context, $data);
            return hash_final($context);
        }

        //Post Control
        if (!$_POST) die("do not have post values");

        //Get Post Values
        $orderID = isset($_POST['order_id']) ? payreksFilter($_POST['order_id']) : null;
        $credit = isset($_POST['credit']) ? payreksFilter($_POST['credit']) : null;
        $userID = isset($_POST['user_id']) ? payreksFilter($_POST['user_id']) : null;
        $userInfo = isset($_POST['user_info']) ? payreksFilter($_POST['user_info']) : null;
        $payLabel = isset($_POST['pay_label']) ? payreksFilter($_POST['pay_label']) : null;
        $totalPrice = isset($_POST['total_price']) ? payreksFilter($_POST['total_price']) : null;
        $netPrice = isset($_POST['net_price']) ? payreksFilter($_POST['net_price']) : null;
        $hash = isset($_POST['hash']) ? $_POST['hash'] : null;

        //Control Post Keys
        if ($orderID === null || $credit === null || $userID === null || $userInfo === null || $payLabel === null ||
            $totalPrice === null || $netPrice === null || $hash === null)
            die("missing value");

        //Control Post Values
        if ($orderID === "" || $credit === "" || $userID === "" || $userInfo === "" || $payLabel === "" ||
            $totalPrice === "" || $netPrice === "" || $hash === "")
            die("empty value");

        $apiKey = $settings['payreks_api_key'];
        $secretKey = $settings['payreks_api_secret'];
        $hashData = md5($orderID . $credit . $userID . $userInfo . $payLabel . $totalPrice . $netPrice . $apiKey);
        $hashCreate = payreksHashControl('sha256', $hashData, $secretKey);
        $get_param = json_decode($credit, true);
        //Hash Control
        if ($hash !== $hashCreate) die("wrong hash");
        $users = User::where('steamid', $userID)->first();
        if (!$users['steamid']) die("user not found");
        if (StorePayment::where(['processor_id' => $orderID, 'processor' => 'payreks'])->exists()) die("order already have");
        //User Control

        $pos_type = array(
            'CREDIT' => 'Kredi Kartı',
            'MOBILE' => 'Mobil Ödeme',
            'EFT' => 'Havale',
            'ININAL' => 'İninal'
        );

        Store::handlePayment([
            'processor' => 'PAYREKS ' . $pos_type[$payLabel],
            'processor_id' => $orderID,
            'total' => $totalPrice * 100,
            'net_total' => $netPrice * 100,
            'currency' => $settings['currency'],
            'type' => 'package', //package--credits
            'quantity' => 1,//1 Paket 0 Kredi
            'package_id' => $credit,
            'steamid' => $userID
        ], $response);
        die("OK");
    }

    public function order($request, $response, $args)
    {
        $settings = Setting::getByCategory('store');
        $params = $request->getParams();
        $total = $request->getAttribute('total');

        $inputOptions = [];

        if ($settings['payreks_payment_1']) {
            $inputOptions[1] = "Kredi Kartı";
        }
        if ($settings['payreks_payment_2']) {
            $inputOptions[2] = "Banka Transferi";
        }
        if ($settings['payreks_payment_3']) {
            $inputOptions[3] = "Mobil Ödeme";
        }
        if ($settings['payreks_payment_4']) {
            $inputOptions[4] = "İninal Ödeme";
        }

        if (!isset($inputOptions[intval($params['gateway'])])) {
            return $response->withJSON([
                'success' => false,
                'message' => "Ödeme metodu artık kullanılmıyor!"
            ]);
        }

        $posData = [
            'steamid' => $this->auth->user()->steamid,
            'name' => $this->auth->user()->name,
            'type' => $params['type'],
            'total' => $total,
            'gateway' => $params['gateway']
        ];

        if ($params['type'] == 'credits') {
            $posData['quantity'] = $params['quantity'];
        } else if ($params['type'] == 'package') {
            $package = $request->getAttribute('package');
            $posData['quantity'] = 1;
            $posData['package_id'] = $package->id;
        }

        $baseUrl = 'https://api.payreks.com/gateway';
        $url = "{$baseUrl}/v2";

        $apiKey = $settings['payreks_api_key'];
        $secretKey = $settings['payreks_api_secret'];
        $userID = $posData['steamid'];
        $userIPAddress = $this->getIPAddress();
        $hashYarat = $this->payreksEncoderHash($apiKey, $secretKey);
        if (intval($posData['gateway']) == 3 && !isset($settings['payreks_payment_commission_type']) && $settings['payreks_mobil_komisyon'] > 0) {
            $total += $total * ((isset($settings['payreks_mobil_komisyon']) ? $settings['payreks_mobil_komisyon'] : 40) / 100);
        }
        $postData = array(
            'api_key' => $apiKey,
            'token' => $hashYarat,
            'return_type' => 'json',
            'user_id' => $userID,
            'user_info' => $posData['name'],
            'user_ip' => $userIPAddress,
            'return_data' => $posData['package_id'],
            'callback_url' => $settings['payreks_callback_url'],
            'redirect_url' => $settings['payreks_success_url'],
            'product_name' => $params['type'] == 'credits' ? 'Kredi' : $package->name,
            'amount' => intval($total / 100),
            'payment' => intval($posData['gateway']),
            'commission_type' => (isset($settings['payreks_payment_commission_type']) ? 2 : 1),
        );
        $postData = http_build_query($postData);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_ENCODING => "",
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $postData,
        ));

        $respon = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        $hata = "";
        if ($err) {
            $hata = $err;
        } else {
            $jsonDecode = json_decode($respon, true);
            if ($jsonDecode['status'] != 200) {
                $hata = $jsonDecode['message'];
            } else {
                return $response->withJSON([
                    'success' => true,
                    'link' => $jsonDecode['link'],
                ]);
            }
        }

        return $response->withJSON([
            'success' => !isset($hata) ? true : false,
            'message' => isset($hata) ? $hata : null
        ]);
    }

    public function payreksEncoderHash($data, $key)
    {
        $context = hash_init("sha256", HASH_HMAC, $key);
        hash_update($context, $data);
        $return = hash_final($context);
        $context2 = hash_init("md5", HASH_HMAC, $key);
        hash_update($context2, $return);
        return hash_final($context2);
    }

    public function getIPAddress()
    {
        $ipAddress = null;
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
        else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if (isset($_SERVER['HTTP_X_FORWARDED']))
            $ipAddress = $_SERVER['HTTP_X_FORWARDED'];
        else if (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
            $ipAddress = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
        else if (isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipAddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if (isset($_SERVER['HTTP_FORWARDED']))
            $ipAddress = $_SERVER['HTTP_FORWARDED'];
        else if (isset($_SERVER['REMOTE_ADDR']))
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipAddress = 'UNKNOWN';
        return $ipAddress;
    }


}
