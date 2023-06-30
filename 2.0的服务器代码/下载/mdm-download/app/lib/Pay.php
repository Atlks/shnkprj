<?php


namespace app\lib;


use think\Exception;

class Pay
{

    protected $mchId = "3536";

    protected $privateKey = "yh3sY5cqT7YJ6rT0sb/r3O64g0AEj1wKq8WRYdnQdNWlX+xUv8F3IqpDXiW8p7+r";

    public function sign($data = [])
    {
        ksort($data);
        reset($data);
        $arg = '';
        foreach ($data as $key => $val) {
            //空值不参与签名
            if ($val == '' || $key == 'sign') {
                continue;
            }
            $arg .= ($key . '=' . $val . '&');
        }
        $arg = $arg . 'key=' . $this->privateKey;

        //签名数据转换为大写
        $sig_data = strtoupper(md5($arg));
        return $sig_data;
    }

    public function getPayUrl($order_no, $money, $callback,$return_url)
    {
        $api = "http://gateway.uuspring.com/payOrder/create";
        $data = [
            "outTradeNo" => $order_no,
            "mchId" => $this->mchId,  //商户ID
            "payProductCode" => 'AlipayH5',
            "orderAmt" => $money,  //订单金额
            "notifyUrl" => $callback, //回调地址
            "returnUrl" => $return_url, //回调地址
        ];
        //私钥签名
        $data['sign'] = $this->sign($data);
        $header = [
            'Content-Type:application/json',
            'Accept: application/json'
        ];
        try {
            $resp = $this->http_requests($api, json_encode($data), $header);
            if($resp){
                $res = json_decode($resp,true);
                if($res&& $res["status"]==0){
                    return $res["data"];
                }else{
                    return false;
                }
            }else{
                return false;
            }
        }catch (Exception $exception){
            return false;
        }
    }


    public function http_requests($url = '', $data = null, $header = [])
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_REFERER, "");
        curl_setopt($curl, CURLOPT_HEADER, 1);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($curl);
        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) == '200') {
            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $body = substr($output, $headerSize);
        }else{
            $body =  null;
        }
        curl_close($curl);
        return $body;
    }

}