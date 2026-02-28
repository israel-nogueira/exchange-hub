<?php
namespace Exchanges\Exchanges\MercadoBitcoin;
use Exchanges\Core\AbstractExchange;
use Exchanges\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};
use Exchanges\Exceptions\OrderNotFoundException;
class MercadoBitcoinExchange extends AbstractExchange
{
    private MercadoBitcoinSigner $signer; private MercadoBitcoinNormalizer $normalizer;
    protected function configure(): void { $this->name='mercadobitcoin'; $this->baseUrl=MercadoBitcoinConfig::BASE_URL; $this->signer=new MercadoBitcoinSigner($this->apiKey,$this->apiSecret); $this->normalizer=new MercadoBitcoinNormalizer(); }

    private function mbUrl(string $tpl, array $vars=[]): string { $url=$this->baseUrl.$tpl; foreach($vars as $k=>$v) $url=str_replace(':'.$k,$v,$url); return $url; }
    private function mbGet(string $url, array $p=[], bool $signed=false): array { $q=$p?'?'.http_build_query($p):''; $fullUrl=$url.$q; $hdrs=[]; if($signed){$h=$this->signer->getHeaders(); foreach($h as $k=>$v) $hdrs[]="$k: $v";} $r=$this->http->get($fullUrl,$hdrs,'mercadobitcoin'); return $r; }
    private function mbPost(string $url, array $b=[]): array { $h=$this->signer->getHeaders(); $hdrs=[]; foreach($h as $k=>$v) $hdrs[]="$k: $v"; return $this->http->post($url,$b,$hdrs,'mercadobitcoin'); }

    public function ping(): bool { try{$this->mbGet($this->baseUrl.MercadoBitcoinConfig::TICKERS);return true;}catch(\Exception $e){return false;} }
    public function getServerTime(): int { return time()*1000; }
    public function getExchangeInfo(): ExchangeInfoDTO { return new ExchangeInfoDTO('Mercado Bitcoin','ONLINE',['BTCBRL','ETHBRL','USDTBRL','BNBBRL','SOLBRL'],0.003,0.007,[],[],time()*1000); }
    public function getSymbols(): array { return ['BTC-BRL','ETH-BRL','USDT-BRL','BNB-BRL','SOL-BRL','ADA-BRL','XRP-BRL']; }
    public function getTicker(string $symbol): TickerDTO { $r=$this->mbGet($this->mbUrl(MercadoBitcoinConfig::TICKER,[':symbol'=>$symbol])); return $this->normalizer->ticker($symbol,(array)$r); }
    public function getTicker24h(string $symbol): TickerDTO { return $this->getTicker($symbol); }
    public function getAllTickers(): array { $r=$this->mbGet($this->baseUrl.MercadoBitcoinConfig::TICKERS); return array_map(fn($t)=>$this->normalizer->ticker($t['symbol']??'',$t),(array)$r); }
    public function getOrderBook(string $symbol, int $limit=20): OrderBookDTO { $r=$this->mbGet($this->mbUrl(MercadoBitcoinConfig::ORDERBOOK,[':symbol'=>$symbol])); return $this->normalizer->orderBook($symbol,(array)$r); }
    public function getRecentTrades(string $symbol, int $limit=50): array { $r=$this->mbGet($this->mbUrl(MercadoBitcoinConfig::TRADES,[':symbol'=>$symbol]),['limit'=>$limit]); return array_map(fn($t)=>new TradeDTO($t['tid']??'',$t['tid']??'',$symbol,$t['type']==='buy'?'BUY':'SELL',(float)$t['price'],(float)$t['amount'],(float)$t['price']*(float)$t['amount'],0,'',false,(int)$t['date']*1000,'mercadobitcoin'),(array)$r); }
    public function getHistoricalTrades(string $symbol, int $limit=100, ?int $fromId=null): array { return $this->getRecentTrades($symbol,$limit); }
    public function getCandles(string $symbol, string $interval='1h', int $limit=100, ?int $startTime=null, ?int $endTime=null): array { $im=['1m'=>60,'5m'=>300,'15m'=>900,'1h'=>3600,'4h'=>14400,'1d'=>86400]; $r=$this->mbGet($this->mbUrl(MercadoBitcoinConfig::CANDLES,[':symbol'=>$symbol]),['resolution'=>$im[$interval]??3600,'to'=>time(),'countBack'=>$limit]); return array_map(fn($c)=>$this->normalizer->candle($symbol,$interval,$c),(array)$r); }
    public function getAvgPrice(string $symbol): float { return $this->getTicker($symbol)->price; }
    public function getAccountInfo(): array { return $this->mbGet($this->baseUrl.MercadoBitcoinConfig::ACCOUNTS,[],true); }
    public function getBalances(): array { $r=$this->mbGet($this->baseUrl.MercadoBitcoinConfig::ACCOUNTS,[],true); $out=[]; foreach((array)$r as $a) { $as=$a['currency']??'BRL'; $out[$as]=$this->normalizer->balance($as,$a); } return $out; }
    public function getBalance(string $asset): BalanceDTO { $r=$this->getBalances(); return $r[strtoupper($asset)]??new BalanceDTO(strtoupper($asset),0,0,0,'mercadobitcoin'); }
    public function getCommissionRates(): array { return ['maker'=>0.003,'taker'=>0.007]; }
    public function getDepositAddress(string $asset, ?string $network=null): DepositDTO { return new DepositDTO(strtoupper($asset),'Consulte painel MB',null,'PIX/TED',null,null,null,DepositDTO::STATUS_CONFIRMED,null,'mercadobitcoin'); }
    public function getDepositHistory(?string $asset=null, ?int $startTime=null, ?int $endTime=null): array { return []; }
    public function getWithdrawHistory(?string $asset=null, ?int $startTime=null, ?int $endTime=null): array { return []; }
    public function withdraw(string $asset, string $address, float $amount, ?string $network=null, ?string $memo=null): WithdrawDTO { return new WithdrawDTO('mb-'.uniqid(),strtoupper($asset),$address,$memo,$network??'PIX',$amount,3.67,$amount-3.67,null,WithdrawDTO::STATUS_PENDING,time()*1000,'mercadobitcoin'); }
    public function createOrder(string $symbol, string $side, string $type, float $quantity, ?float $price=null, ?float $stopPrice=null, ?string $timeInForce='GTC', ?string $clientOrderId=null): OrderDTO { $b=['type'=>strtolower($type),'side'=>strtolower($side),'qty'=>(string)$quantity]; if($price) $b['limitPrice']=(string)$price; $r=$this->mbPost($this->mbUrl(MercadoBitcoinConfig::ORDERS,[':symbol'=>$symbol]),$b); return $this->normalizer->order(array_merge(['instrument'=>$symbol],(array)$r)); }
    public function cancelOrder(string $symbol, string $orderId): OrderDTO { $o=$this->getOrder($symbol,$orderId); $url=$this->mbUrl(MercadoBitcoinConfig::ORDER,[':symbol'=>$symbol,':orderId'=>$orderId]); $h=$this->signer->getHeaders(); $hdrs=[]; foreach($h as $k=>$v) $hdrs[]="$k: $v"; $this->http->delete($url,$hdrs,'mercadobitcoin'); return $o; }
    public function cancelAllOrders(string $symbol): array { $open=$this->getOpenOrders($symbol); foreach($open as $o) $this->cancelOrder($symbol,$o->orderId); return $open; }
    public function getOrder(string $symbol, string $orderId): OrderDTO { $r=$this->mbGet($this->mbUrl(MercadoBitcoinConfig::ORDER,[':symbol'=>$symbol,':orderId'=>$orderId]),[],true); if(empty($r)) throw new OrderNotFoundException($orderId,'mercadobitcoin'); return $this->normalizer->order(array_merge(['instrument'=>$symbol],(array)$r)); }
    public function getOpenOrders(?string $symbol=null): array { if(!$symbol) return []; $r=$this->mbGet($this->mbUrl(MercadoBitcoinConfig::ORDERS,[':symbol'=>$symbol]),['status'=>'open'],true); return array_map(fn($o)=>$this->normalizer->order(array_merge(['instrument'=>$symbol],$o)),(array)$r); }
    public function getOrderHistory(string $symbol, int $limit=100, ?int $startTime=null, ?int $endTime=null): array { $r=$this->mbGet($this->mbUrl(MercadoBitcoinConfig::ORDERS,[':symbol'=>$symbol]),['limit'=>$limit],true); return array_map(fn($o)=>$this->normalizer->order(array_merge(['instrument'=>$symbol],$o)),(array)$r); }
    public function getMyTrades(string $symbol, int $limit=100, ?int $startTime=null, ?int $endTime=null): array { return $this->getRecentTrades($symbol,$limit); }
    public function editOrder(string $symbol, string $orderId, ?float $price=null, ?float $quantity=null): OrderDTO { $original=$this->getOrder($symbol,$orderId); $this->cancelOrder($symbol,$orderId); return $this->createOrder($symbol,$original->side,$original->type,$quantity??$original->quantity,$price??$original->price); }
    public function createOCOOrder(string $symbol, string $side, float $quantity, float $price, float $stopPrice, float $stopLimitPrice): array { $l=$this->createOrder($symbol,$side,'LIMIT',$quantity,$price); $s=$this->createOrder($symbol,$side,'LIMIT',$quantity,$stopLimitPrice); return ['oco_group_id'=>null,'limit_order'=>$l,'stop_order'=>$s]; }
    public function stakeAsset(string $asset, float $amount): array { return ['asset'=>strtoupper($asset),'staked'=>$amount,'status'=>'STAKED','note'=>'Não disponível via API']; }
    public function unstakeAsset(string $asset, float $amount): array { return ['asset'=>strtoupper($asset),'unstaked'=>$amount,'status'=>'UNSTAKED']; }
    public function getStakingPositions(): array { return []; }
}
