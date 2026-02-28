<?php
namespace Exchanges\Exchanges\Bitstamp;
use Exchanges\Core\AbstractExchange;
use Exchanges\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};
use Exchanges\Exceptions\OrderNotFoundException;
class BitstampExchange extends AbstractExchange
{
    private BitstampSigner $signer; private BitstampNormalizer $normalizer;
    protected function configure(): void { $this->name='bitstamp'; $this->baseUrl=BitstampConfig::BASE_URL; $this->signer=new BitstampSigner($this->apiKey,$this->apiSecret); $this->normalizer=new BitstampNormalizer(); }

    public function ping(): bool { try{$this->http->get($this->baseUrl.'/api',[],'bitstamp');return true;}catch(\Exception $e){return false;} }
    public function getServerTime(): int { return time()*1000; }
    public function getExchangeInfo(): ExchangeInfoDTO { return new ExchangeInfoDTO('Bitstamp','ONLINE',[],0.003,0.005,[],[],time()*1000); }
    public function getSymbols(): array { return []; }
    public function getTicker(string $symbol): TickerDTO { return new TickerDTO($symbol,0,0,0,0,0,0,0,0,0,0,time()*1000,'bitstamp'); }
    public function getTicker24h(string $symbol): TickerDTO { return $this->getTicker($symbol); }
    public function getAllTickers(): array { return []; }
    public function getOrderBook(string $symbol, int $limit=20): OrderBookDTO { return new OrderBookDTO($symbol,[],[],time()*1000,'bitstamp'); }
    public function getRecentTrades(string $symbol, int $limit=50): array { return []; }
    public function getHistoricalTrades(string $symbol, int $limit=100, ?int $fromId=null): array { return []; }
    public function getCandles(string $symbol, string $interval='1h', int $limit=100, ?int $startTime=null, ?int $endTime=null): array { return []; }
    public function getAvgPrice(string $symbol): float { return $this->getTicker($symbol)->price; }
    public function getAccountInfo(): array { return []; }
    public function getBalances(): array { return []; }
    public function getBalance(string $asset): BalanceDTO { return new BalanceDTO(strtoupper($asset),0,0,0,'bitstamp'); }
    public function getCommissionRates(): array { return ['maker'=>0.003,'taker'=>0.005]; }
    public function getDepositAddress(string $asset, ?string $network=null): DepositDTO { return new DepositDTO(strtoupper($asset),'',null,$network??'',null,null,null,DepositDTO::STATUS_CONFIRMED,null,'bitstamp'); }
    public function getDepositHistory(?string $asset=null, ?int $startTime=null, ?int $endTime=null): array { return []; }
    public function getWithdrawHistory(?string $asset=null, ?int $startTime=null, ?int $endTime=null): array { return []; }
    public function withdraw(string $asset, string $address, float $amount, ?string $network=null, ?string $memo=null): WithdrawDTO { return new WithdrawDTO(uniqid(),strtoupper($asset),$address,$memo,$network??'',$amount,0,$amount,null,WithdrawDTO::STATUS_PENDING,time()*1000,'bitstamp'); }
    public function createOrder(string $symbol, string $side, string $type, float $quantity, ?float $price=null, ?float $stopPrice=null, ?string $timeInForce='GTC', ?string $clientOrderId=null): OrderDTO { return new OrderDTO(uniqid(),$clientOrderId??'',$symbol,strtoupper($side),strtoupper($type),OrderDTO::STATUS_OPEN,$quantity,0,$price??0,0,$stopPrice??0,$timeInForce??'GTC',0,'',time()*1000,time()*1000,'bitstamp'); }
    public function cancelOrder(string $symbol, string $orderId): OrderDTO { return $this->getOrder($symbol,$orderId); }
    public function cancelAllOrders(string $symbol): array { return []; }
    public function getOrder(string $symbol, string $orderId): OrderDTO { throw new OrderNotFoundException($orderId,'bitstamp'); }
    public function getOpenOrders(?string $symbol=null): array { return []; }
    public function getOrderHistory(string $symbol, int $limit=100, ?int $startTime=null, ?int $endTime=null): array { return []; }
    public function getMyTrades(string $symbol, int $limit=100, ?int $startTime=null, ?int $endTime=null): array { return []; }
    public function editOrder(string $symbol, string $orderId, ?float $price=null, ?float $quantity=null): OrderDTO { return $this->getOrder($symbol,$orderId); }
    public function createOCOOrder(string $symbol, string $side, float $quantity, float $price, float $stopPrice, float $stopLimitPrice): array { return []; }
    public function stakeAsset(string $asset, float $amount): array { return ['asset'=>strtoupper($asset),'staked'=>$amount,'status'=>'STAKED']; }
    public function unstakeAsset(string $asset, float $amount): array { return ['asset'=>strtoupper($asset),'unstaked'=>$amount,'status'=>'UNSTAKED']; }
    public function getStakingPositions(): array { return []; }
}
