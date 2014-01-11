<?php
class Bitstamp
{
    private $_apiKey;
    private $_apiSecret;
    private $_clientId;

    private $_result;
    private $_ticker;
    private $_balance;
    private $_eurToUsd;
    private $_withdrawalRequests;
    private $_depositAddress;
    private $_unconfirmedDeposits;
    private $_rippleDepositAddress;

    const API_ENDPOINT = 'https://www.bitstamp.net/api/';

    /**
     * Constructor
     *
     * @param string api key $apiKey
     * @param string secret $secret
     * @param stirng client id $clientId
     * @return
     */
    public function __construct($apiKey = null, $secret = null, $clientId = null)
    {
        if (empty($apiKey) || empty($secret) || empty($clientId)) {
            $this->error(
                'Missing API key, secret, or clientId'
            );
        }

        $this->_apiKey = $apiKey;
        $this->_apiSecret = $secret;
        $this->_clientId = $clientId;
    }

    /**
     * API query
     *
     * @param string $path
     * @param array $request`
     * @param array $headers
     * @param string $method
     * @return mixed
     */
    public function query($path, array $request = array(), array $headers = array(), $method = 'GET')
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT,
            'Mozilla/4.0 (compatible; Bitstamp PHP Client v0; ' .
            php_uname('s') .
            '; PHP/' .
            phpversion() . ')'
        );

        switch (strtoupper($method)) {
            case 'GET':
                $url = self::API_ENDPOINT . $path . '/';

                if (!empty($request)) {
                    $url .= '?';
                    $url .= http_build_query($request, '', '&');
                }

                curl_setopt(
                    $ch,
                    CURLOPT_URL,
                    $url
                );
                break;

            case 'POST':
                $mt = explode(' ', microtime());
                $request['nonce'] = $mt[1] . substr($mt[0], 2, 6);
                $request['key'] = $this->_apiKey;
                $request['signature'] = $this->_getSignature(
                    $request['nonce']
                );

                curl_setopt($ch,
                    CURLOPT_URL,
                    self::API_ENDPOINT . $path .'/'
                );

                curl_setopt(
                    $ch,
                    CURLOPT_POSTFIELDS,
                    http_build_query($request, '', '&')
                );
                break;

            default:
                $this->error("Unsupported cURL HTTP method \"{$method}\".");
                break;
        }


        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if ($response === false) {
            echo "Bitstamp error, trying again...";
            sleep(2);
            return false;
        }

        $this->_result = json_decode($response, true);

        if (is_null($this->_result)) {
            echo "Bitstamp error, trying again...";
            sleep(2);
            return false;
        }

        return $this->_result;
    }

    /**
     * Get account details associated with the current API Authentication.
     *
     * @require API Rights: Get Info
     * @return mixed
     */
    function getInfo()
    {
        $result = $this->getBalance();
        if (isset($result['error'])) {
            $return = $this->_wrapResult('error', $result['error']);
        } else {
            $return = array(
                'Login' => 'N/A',
                'Trade_Fee' => $result['fee'],
                'Wallets' => array(
                    'BTC' => array(
                        'Balance' => array(
                            'value' => $result['btc_balance']
                        )
                    ),
                    'USD' => array(
                        'Balance' => array(
                            'value' => $result['usd_balance']
                        )
                    )
                )
            );

            $return = $this->_wrapResult('success', $return);
        }

        return $return;
    }

    /**
     * Get ticker information.
     *
     * @return array
     */
    function getTicker()
    {
        $ticker = $this->getBitstampTicker();

        if (isset($ticker['error'])) {
            $return = $this->_wrapResult('error', $ticker['error']);
        } else {
            $return = array(
                'last' => array('value' => $ticker['last']),
                'high' => array('value' => $ticker['high']),
                'low' => array('value' => $ticker['low']),
                'avg' => array('value' => number_format(
                    ($ticker['high'] + $ticker['low']) / 2,
                    2
                )),
                'buy' => array('value' => $ticker['bid']),
                'sell' => array('value' => $ticker['ask']),
                'vol' => array('value' => $ticker['volume'])
            );

            $return = $this->_wrapResult('success', $return);
        }

        return $return;
    }

    /**
     * Get currency information.
     *
     * @return array
     */
    function getCurrency()
    {
        $this->_result = array(
            "result" => "success",
            "data" => array(
                "currency" => "USD",
                "decimals" => "5",
                "depth_channel" => "24e67e0d-1cad-4cc0-9e7a-f8523ef460fe",
                "display_decimals" => "2",
                "index" => "2",
                "name" => "Dollar",
                "symbol" => "$",
                "symbol_position" => "before",
                "ticker_channel" => "d5f06780-30a8-4a48-a2f8-7ed181b4a13f",
                "virtual" => "N"
        ));
        //mocked, not used really

        return $this->_result;
    }

    /**
     * Get information on current orders.
     * ***** CONVENIENCE TO MATCH MTGOX CLASS *****
     *
     * @return mixed
     */
    function getOrders()
    {
        $result = $this->getOpenOrders();

        if (isset($result['error'])) {
            $return = $this->_wrapResult('error', $result['error']);
        } else {
            $return = $this->_wrapResult('success', $result);
        }

        return $return;
    }

    /**
     * Get an up-to-date quote for a bid or ask transaction.
     *
     * @param string $type
     * @param string $amount
     * @return Array
     */
    function orderQuote($type = 'ask', $amount = '100000000')
    {
        // stub, bitstamp doesn't have this
        $this->_result = array(
            'result' => 'success',
            'data' => array(
                'amount' => 31337.0000
        ));

        return $this->_result;
    }

    /**
     * Place a bid order of a specific amount and bid price.
     *
     * @param float $amount
     * @param $price
     * @return array
     */
    function orderBuy($amount = 0.0001, $price = null)
    {
        return $this->orderAdd('bid', $amount, $price);
    }

    /**
     * Place an ask order of a specific amount and ask price.
     *
     * @param float $amount
     * @param $price
     * @return array
     */
    function orderSell($amount = 0.0001, $price = null)
    {
        return $this->orderAdd('ask', $amount, $price);
    }

    /**
     * Place an order of a specific amount and bid/ask price.
     *
     * @param $type
     * @param float $amount
     * @param $price
     * @return array
     */
    function orderAdd($type, $amount = 0.0001, $price = null)
    {
        if (!in_array($type, array('bid', 'ask'))) {
            $this->error(
                'You must specify a type: bid or ask'
            );
        }

        if(is_null($price)) {
            if (empty($this->_ticker)) {
                $this->getTicker();
            }

            $price = $this->_ticker[$type];
        }

        switch ($type) {
            case 'bid':
                $cmd = 'buy';
                break;

            case 'ask':
                $cmd = 'sell';
                break;
        }

        $result = $this->query(
            $cmd,
            array(
                'amount' => $amount,
                'price' => $price
            ),
            array(),
            'POST'
        );

        /*****/

        if (isset($result['error'])) {
            $return = $this->_wrapResult('error', $result['error']);
        } else {
            $return = $this->_wrapResult('success', $result['id']);
        }

        return $return;
    }

    /**
     * Cancels an order by Order ID.
     *
     * @param mixed $orderId
     * @return array
     */
    function orderCancel($orderId = null)
    {
        if (empty($orderId)) {
            $this->error(
                'Missing orderId to cancel'
            );
        }

        $result = $this->query(
            'cancel_order',
            array(
                'id' => $orderId
            ),
            array(),
            'POST'
        );

        if (isset($result['error'])) {
            $return = $this->_wrapResult('error', $result['error']);
        } else {
            $return = $this->_wrapResult('success', array('oid' => $orderId));
        }

        return $return;
    }

    /**
     * Returns current ticker from Bitstamp
     *
     * @return mixed
     */
    function getBitstampTicker()
    {
        $this->_ticker = $this->query('ticker');

        return $this->_ticker;
    }

    /*****
    /**
     * Returns JSON dictionary with "bids" and "asks". Each is a list of open
     * orders and each order is represented as a list of price and amount.
     *
     * @param int $group
     */
    function getOrderBook($group = 1)
    {
        return $this->query(
            'order_book',
            array(
                'group' => $group
        ));
    }

    /**
    * Returns descending JSON list of transactions.
    *
    * @param string $time
    */
    function getTransactions($time = 'hour')
    {
        return $this->query(
            'transactions',
            array(
                'time' => $time
        ));
    }

    /**
     * Returns current EUR/USD rate from Bitstamp
     *
     * @return mixed
     */
    function getEurToUsd()
    {
        $this->_eurToUsd = $this->query('eur_usd');

        return $this->_eurToUsd;
    }

    /**
     * Fetch account's wallet balance
     *
     * @return mixed
     */
    function getBalance()
    {
        $this->_balance = $this->query(
            'balance',
            array(),
            array(),
            'POST'
        );

        return $this->_balance;
    }

    /**
     * Return account transactions based on offset, limit, and direction
     *
     * @param int $offset
     * @param int $limit
     * @param string $sort
     * @return mixed
     */
    function getUserTransactions($offset = 0, $limit = 100, $sort = 'desc')
    {
        return $this->query(
            'user_transactions',
            array(
                'offset' => (int)$offset,
                'limit' => (int)$limit,
                'sort' => strtolower(
                    (string)$sort
            )),
            array(),
            'POST'
        );
    }

    /**
     * Get information on current orders.
     *
     * @return mixed
     */
    function getOpenOrders()
    {
        return $this->query(
            'open_orders',
            array(),
            array(),
            'POST'
        );
    }

    /**
     * Returns JSON list of withdrawal requests.
     * Each request is represented as dictionary:
     *
     * @return mixed
     */
    function getWithdrawalRequests()
    {
        $this->_withdrawalRequests = $this->query(
            'withdrawal_requests',
            array(),
            array(),
            'POST'
        );

        return $this->_withdrawalRequests;
    }

    /**
     * Returns result of bitcoin amount withdrawal from address.
     *
     * @return mixed
     */
    function getBitcoinWithdrawal($amount = null, $address = null)
    {
        if (empty($amount) || empty($address)) {
            $this->error(
                'Missing bitcoin withdrawal amount or address'
            );
        }

        return $this->query(
            'bitcoin_withdrawal',
            array(
                'amount' => $amount,
                'address' => $address
            ),
            array(),
            'POST'
        );
    }

    /**
     * Returns bitcoin deposit address..
     *
     * @return mixed
     */
    function getBitcoinDepositAddress()
    {
        $this->_depositAddress = $this->query(
            'bitcoin_deposit_address',
            array(),
            array(),
            'POST'
        );

        return $this->_depositAddress;
    }

    /**
     * Returns JSON list of unconfirmed bitcoin transactions.
     *
     * @return mixed
     */
    function getUnconfirmedDeposits()
    {
        $this->_unconfirmedDeposits = $this->query(
            'unconfirmed_btc',
            array(),
            array(),
            'POST'
        );

        return $this->_unconfirmedDeposits;
    }

    /**
     * Returns true if successful.
     *
     * @param float $amount
     * @param string $address
     * @param string $currency
     * @return mixed
     */
    function getRippleWithdrawal($amount = 0.0001, $address = null, $currency = null)
    {
        if (empty($amount) || empty($address) || empty($currency)) {
            $this->error(
                'Missing ripple withdrawal amount, address, or currency'
            );
        }

        return $this->query(
            'ripple_address',
            array(
                'amount' => $amount,
                'address' => $address,
                'currency' => $currency
            ),
            array(),
            'POST'
        );
    }


    /**
     * Returns your ripple deposit address.
     *
     * @return mixed
     */
    function getRippleAddress($amount = 0.0001, $address, $currency)
    {
        $this->_rippleAddress = $this->query(
            'ripple_address',
            array(),
            array(),
            'POST'
        );

        return $this->_rippleAddress;
    }

    /**
     * Compute bitstamp signature

     * @param float $nonce
     * return string
     */
    private function _getSignature($nonce)
    {
        $message = $nonce. $this->_clientId. $this->_apiKey;

        return strtoupper(
            hash_hmac(
                'sha256',
                $message,
                $this->_apiSecret
        ));
    }

    /**
     * Throws errors.
     *
     * @param $message
     * @throws Exception
     */
    function error($message)
    {
        throw new Exception($message);
    }

    /**
     * Generically wrap Bitstamp data as loose Mt. Gox data
     *
     * @param string $result
     * @param mixed $data
     */
    private function _wrapResult($result, $data = null)
    {
        return array(
            'result' => $result,
            'data' => $data
        );
    }
}