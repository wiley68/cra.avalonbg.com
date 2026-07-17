<?php

namespace App\Enums;

enum ScopeQuestionKey: string
{
    case ProductKind = 'product_kind';
    case CommercialActivity = 'commercial_activity';
    case NetworkOrDeviceLink = 'network_or_device_link';
    case OfferedStandalone = 'offered_standalone';
    case SoldUnderOwnBrand = 'sold_under_own_brand';
    case RemoteProcessingRequired = 'remote_processing_required';
    case OtherSectorRegulation = 'other_sector_regulation';
    case ComponentOfOtherProduct = 'component_of_other_product';
    case FreeOpenSource = 'free_open_source';
    case SubstantialModification = 'substantial_modification';
    case MarketRole = 'market_role';
    case OfferedInEu = 'offered_in_eu';

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return self::cases();
    }
}
