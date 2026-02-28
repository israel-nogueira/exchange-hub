<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Fake;

class FakeConfig
{
    public string $exchangeName          = 'FakeExchange';
    public float  $makerFee              = 0.001;
    public float  $takerFee              = 0.001;
    public float  $priceVolatility       = 0.005;
    public int    $orderBookDepth        = 20;
    public bool   $autoExecuteLimitOrders= true;
    public string $dataPath              = '';

    public array $supportedFiats = ['USDT', 'USDC', 'BRL', 'BUSD'];

    public array $initialBalances = [
        'USDT' => 10000.00,
        'BTC'  => 1.5,
        'ETH'  => 10.0,
        'BNB'  => 50.0,
        'SOL'  => 100.0,
        'ADA'  => 5000.0,
        'BRL'  => 50000.00,
    ];

    public array $basePrices = [
        'BTCUSDT'   => 98500.00,
        'ETHUSDT'   => 3850.00,
        'BNBUSDT'   => 710.00,
        'SOLUSDT'   => 185.00,
        'ADAUSDT'   => 0.92,
        'XRPUSDT'   => 2.35,
        'DOGEUSDT'  => 0.38,
        'DOTUSDT'   => 9.80,
        'MATICUSDT' => 1.05,
        'LINKUSDT'  => 19.50,
        'LTCUSDT'   => 112.00,
        'UNIUSDT'   => 14.20,
        'ATOMUSDT'  => 11.30,
        'AVAXUSDT'  => 42.50,
        'BTCBRL'    => 492500.00,
        'ETHBRL'    => 19250.00,
    ];

    public array $depositNetworks = [
        'BTC'  => ['BTC'   => '1FakeAddressBTC123456789abc'],
        'ETH'  => ['ERC20' => '0xFakeEthAddress123456789abcdef'],
        'BNB'  => ['BEP20' => '0xFakeBNBAddress123456789abcdef'],
        'SOL'  => ['SOL'   => 'FakeSolanaAddress123456789abcdefghij'],
        'USDT' => ['ERC20' => '0xFakeUSDTERC20Address', 'TRC20' => 'TFakeUSDTTRC20Address', 'BEP20' => '0xFakeUSDTBEP20Address'],
        'BRL'  => ['PIX'   => 'fake@pix.com.br', 'TED' => 'Ag: 0001 CC: 123456-7'],
    ];

    public array $withdrawFees = [
        'BTC'  => 0.0005,
        'ETH'  => 0.005,
        'BNB'  => 0.001,
        'USDT' => 1.00,
        'SOL'  => 0.01,
        'BRL'  => 3.67,
    ];

    public static function fromArray(array $config): self
    {
        $obj = new self();
        foreach ($config as $key => $value) {
            if (property_exists($obj, $key)) {
                $obj->$key = $value;
            }
        }
        return $obj;
    }
}
