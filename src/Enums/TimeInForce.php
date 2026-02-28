<?php

namespace IsraelNogueira\ExchangeHub\Enums;

enum TimeInForce: string
{
    /** Good Till Cancelled — permanece ativa até ser executada ou cancelada */
    case GTC = 'GTC';

    /** Immediate Or Cancel — executa o máximo possível, cancela o restante */
    case IOC = 'IOC';

    /** Fill Or Kill — executa totalmente ou cancela integralmente */
    case FOK = 'FOK';

    /** Good Till Date — permanece ativa até uma data/hora específica */
    case GTD = 'GTD';
}
