<?php
namespace Exchanges\Exchanges\Okx;
use Exchanges\Core\AbstractExchange;
use Exchanges\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};
use Exchanges\Exceptions\OrderNotFoundException;

class OkxExchange extends AbstractExchange
{
    private OkxSigner     $signer;
    private OkxNormalizer $normalizer;
    private bool          $demo = false;

    protected function configure(): void
    {
        $this->name       = 'okx';
        $this->baseUrl    = OkxConfig::BASE_URL;
        $this->demo       = $this->options['demo'] ?? $this->testnet;
        $this->signer     = new OkxSigner($this->apiKey, $this->apiSecret, $this->passphrase);
        $this->normalizer = new OkxNormalizer();
    }

    private function okxGet(string $path, array $params = [], bool $signed = false): array
    {
        $query = $params ? '?' . http_build_query($params) : '';
        $url   = $this->baseUrl . $path . $query;
        $headers = $signed ? $this->signer->getHeaders('GET', $path . $query, '', $this->demo) : ['Content-Type: application/json'];
        $hdrs = []; foreach ($headers as $k=>$v) $hdrs[] = is_int($k)?"$v":"$k: $v";
        $res = $this->http->get($url, $hdrs, 'okx');
        return $res['data'] ?? $res;
    }

    private function okxPost(string $path, array $body = []): array
    {
        $bodyStr = json_encode($body);
        $url     = $this->baseUrl . $path;
        $headers = $this->signer->getHeaders('POST', $path, $bodyStr, $this->demo);
        $hdrs = []; foreach ($headers as $k=>$v) $hdrs[] = "$k: $v";
        $res = $this->http->post($url, $bodyStr, $hdrs, 'okx');
        return $res['data'] ?? $res;
    }

    public function ping(): bool { try { $this->okxGet(OkxConfig::TICKERS,['instType'=>'SPOT']); return true; } catch(\Exception $e) { return false; } }
    public function getServerTime(): int { return time()*1000; }
    public function getExchangeInfo(): ExchangeInfoDTO { $r=$this->okxGet(OkxConfig::INSTRUMENTS,['instType'=>'SPOT']); return new ExchangeInfoDTO('OKX','ONLINE',array_map(fn($s)=>$s['instId'],$r),0.0008,0.001,[],[],time()*1000); }
    public function getSymbols(): array { return array_map(fn($s)=>$s['instId'],$this->okxGet(OkxConfig::INSTRUMENTS,['instType'=>'SPOT'])); }
    public function getTicker(string $symbol): TickerDTO { $r=$this->okxGet(OkxConfig::TICKER,['instId'=>$symbol]); return $this->normalizer->ticker($r[0]??[]); }
    public function getTicker24h(string $symbol): TickerDTO { return $this->getTicker($symbol); }
    public function getAllTickers(): array { $r=$this->okxGet(OkxConfig::TICKERS,['instType'=>'SPOT']); return array_map(fn($t)=>$this->normalizer->ticker($t),$r); }
    public function getOrderBook(string $symbol, int $limit = 20): OrderBookDTO { $raw=$this->http->get($this->baseUrl.OkxConfig::BOOKS.'?instId='.$symbol.'&sz='.$limit,[],  'okx'); return $this->normalizer->orderBook($raw,$symbol); }
    public function getRecentTrades(string $symbol, int $limit = 50): array { $r=$this->okxGet(OkxConfig::TRADES,['instId'=>$symbol,'limit'=>$limit]); return array_map(fn($t)=>$this->normalizer->trade($t),$r); }
    public function getHistoricalTrades(string $symbol, int $limit = 100, ?int $fromId = null): array { return $this->getRecentTrades($symbol,$limit); }
    public function getCandles(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $bar=$this->okxIntervalMap($interval);
        $params=['instId'=>$symbol,'bar'=>$bar,'limit'=>$limit];
        if($startTime) $params['before']=$startTime;
        $r=$this->okxGet(OkxConfig::CANDLES,$params);
        return array_map(fn($c)=>$this->normalizer->candle($symbol,$interval,$c),$r);
    }
    public function getAvgPrice(string $symbol): float { return $this->getTicker($symbol)->price; }

    public function getAccountInfo(): array { return $this->okxGet(OkxConfig::ACCOUNT_CONFIG,[],true); }
    public function getBalances(): array
    {
        $r=$this->okxGet(OkxConfig::ACCOUNT,[],true);
        $out=[];
        foreach($r[0]['details']??[] as $d) { if((float)$d['cashBal']>0) $out[$d['ccy']]=$this->normalizer->balance($d['ccy'],$d); }
        return $out;
    }
    public function getBalance(string $asset): BalanceDTO
    {
        $r=$this->okxGet(OkxConfig::ACCOUNT,['ccy'=>strtoupper($asset)],true);
        $detail = $r[0]['details'][0] ?? [];
        return $this->normalizer->balance(strtoupper($asset), $detail);
    }
    public function getCommissionRates(): array { return ['maker'=>0.0008,'taker'=>0.001]; }
    public function getDepositAddress(string $asset, ?string $network = null): DepositDTO
    {
        $p=['ccy'=>strtoupper($asset)]; if($network) $p['chain']=$network;
        $r=$this->okxGet(OkxConfig::DEPOSIT_ADDRESS,$p,true);
        return $this->normalizer->depositAddress($asset,$r[0]??[]);
    }
    public function getDepositHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $p=[]; if($asset) $p['ccy']=strtoupper($asset);
        $r=$this->okxGet(OkxConfig::DEPOSIT_HISTORY,$p,true);
        return array_map(fn($d)=>$this->normalizer->depositAddress($d['ccy']??'',$d),$r);
    }
    public function getWithdrawHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $p=[]; if($asset) $p['ccy']=strtoupper($asset);
        $r=$this->okxGet(OkxConfig::WITHDRAWAL_HISTORY,$p,true);
        return array_map(fn($w)=>$this->normalizer->withdraw($w),$r);
    }
    public function withdraw(string $asset, string $address, float $amount, ?string $network = null, ?string $memo = null): WithdrawDTO
    {
        $b=['ccy'=>strtoupper($asset),'amt'=>$amount,'dest'=>4,'toAddr'=>$address,'fee'=>'0'];
        if($network) $b['chain']=$network; if($memo) $b['tag']=$memo;
        $r=$this->okxPost(OkxConfig::WITHDRAWAL,$b);
        return new WithdrawDTO($r[0]['wdId']??'',strtoupper($asset),$address,$memo,$network??'',$amount,0,$amount,null,WithdrawDTO::STATUS_PENDING,time()*1000,'okx');
    }
    public function createOrder(string $symbol, string $side, string $type, float $quantity, ?float $price = null, ?float $stopPrice = null, ?string $timeInForce = 'GTC', ?string $clientOrderId = null): OrderDTO
    {
        $typeMap=['MARKET'=>'market','LIMIT'=>'limit','STOP_LIMIT'=>'limit','STOP_MARKET'=>'market'];
        $b=['instId'=>$symbol,'tdMode'=>'cash','side'=>strtolower($side),'ordType'=>$typeMap[strtoupper($type)]??'limit','sz'=>$quantity];
        if($price) $b['px']=$price; if($clientOrderId) $b['clOrdId']=$clientOrderId;
        $r=$this->okxPost(OkxConfig::ORDER,$b);
        return $this->getOrder($symbol,$r[0]['ordId']??'');
    }
    public function cancelOrder(string $symbol, string $orderId): OrderDTO
    {
        $order=$this->getOrder($symbol,$orderId);
        $this->okxPost(OkxConfig::CANCEL_ORDER,['instId'=>$symbol,'ordId'=>$orderId]);
        return $order;
    }
    public function cancelAllOrders(string $symbol): array
    {
        $open=$this->getOpenOrders($symbol);
        $batch=array_map(fn($o)=>['instId'=>$symbol,'ordId'=>$o->orderId],$open);
        if(!empty($batch)) $this->okxPost(OkxConfig::CANCEL_BATCH,$batch);
        return $open;
    }
    public function getOrder(string $symbol, string $orderId): OrderDTO
    {
        $r=$this->okxGet(OkxConfig::ORDER,['instId'=>$symbol,'ordId'=>$orderId],true);
        if(empty($r[0])) throw new OrderNotFoundException($orderId,'okx');
        return $this->normalizer->order($r[0]);
    }
    public function getOpenOrders(?string $symbol = null): array
    {
        $p=['instType'=>'SPOT']; if($symbol) $p['instId']=$symbol;
        $r=$this->okxGet(OkxConfig::ORDERS_PENDING,$p,true);
        return array_map(fn($o)=>$this->normalizer->order($o),$r);
    }
    public function getOrderHistory(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $r=$this->okxGet(OkxConfig::ORDERS_HISTORY,['instType'=>'SPOT','instId'=>$symbol,'limit'=>$limit],true);
        return array_map(fn($o)=>$this->normalizer->order($o),$r);
    }
    public function getMyTrades(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $r=$this->okxGet(OkxConfig::FILLS,['instType'=>'SPOT','instId'=>$symbol,'limit'=>$limit],true);
        return array_map(fn($t)=>$this->normalizer->trade($t),$r);
    }
    public function editOrder(string $symbol, string $orderId, ?float $price = null, ?float $quantity = null): OrderDTO
    {
        $b=['instId'=>$symbol,'ordId'=>$orderId];
        if($price) $b['newPx']=$price; if($quantity) $b['newSz']=$quantity;
        $r=$this->okxPost(OkxConfig::AMEND_ORDER,$b);
        return $this->getOrder($symbol,$r[0]['ordId']??$orderId);
    }
    public function createOCOOrder(string $symbol, string $side, float $quantity, float $price, float $stopPrice, float $stopLimitPrice): array
    {
        $limit=$this->createOrder($symbol,$side,'LIMIT',$quantity,$price);
        $stop=$this->createOrder($symbol,$side,'LIMIT',$quantity,$stopLimitPrice);
        return ['oco_group_id'=>null,'limit_order'=>$limit,'stop_order'=>$stop];
    }
    public function stakeAsset(string $asset, float $amount): array
    {
        $offers=$this->okxGet(OkxConfig::EARN_OFFERS,['ccy'=>strtoupper($asset)],true);
        $productId=$offers[0]['productId']??null;
        if(!$productId) throw new \RuntimeException("Produto Earn não encontrado para {$asset}");
        $r=$this->okxPost(OkxConfig::EARN_PURCHASE,['productId'=>$productId,'investData'=>[['ccy'=>strtoupper($asset),'amt'=>$amount]],'term'=>0]);
        return ['asset'=>strtoupper($asset),'staked'=>$amount,'order_id'=>$r[0]['ordId']??null,'status'=>'STAKED'];
    }
    public function unstakeAsset(string $asset, float $amount): array
    {
        $active=$this->okxGet(OkxConfig::EARN_ACTIVE,['ccy'=>strtoupper($asset)],true);
        $ordId=$active[0]['ordId']??null;
        if(!$ordId) throw new \RuntimeException("Posição Earn não encontrada para {$asset}");
        $r=$this->okxPost(OkxConfig::EARN_REDEEM,['ordId'=>$ordId,'protocolType'=>'defi','allowEarlyRedeem'=>true]);
        return ['asset'=>strtoupper($asset),'unstaked'=>$amount,'status'=>'UNSTAKED'];
    }
    public function getStakingPositions(): array { return $this->okxGet(OkxConfig::EARN_ACTIVE,[],true); }

    /** Define alavancagem */
    public function setLeverage(string $symbol, int $leverage, string $mode = 'cross'): array
    {
        return $this->okxPost(OkxConfig::SET_LEVERAGE,['instId'=>$symbol,'lever'=>$leverage,'mgnMode'=>$mode]);
    }
    /** Fecha posição completamente */
    public function closePosition(string $symbol, string $mode = 'cross'): array
    {
        return $this->okxPost(OkxConfig::CLOSE_POSITION,['instId'=>$symbol,'mgnMode'=>$mode]);
    }
    /** Funding rate atual */
    public function getFundingRate(string $symbol): array
    {
        return $this->okxGet(OkxConfig::FUNDING_RATE,['instId'=>$symbol]);
    }

    private function okxIntervalMap(string $interval): string
    {
        return OkxConfig::INTERVAL_MAP[$interval] ?? '1H';
    }
}
