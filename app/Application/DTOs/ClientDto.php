<?php

namespace App\Application\DTOs;

use App\Domain\Entities\Client;

class ClientDto
{
    public static function fromEntity(Client $client): array
    {
        return [
            'accountid' => $client->accountid,
            'account_no' => $client->account_no,
            'accountname' => $client->accountname,
            'parentid' => $client->parentid,
            'account_type' => $client->account_type,
            'industry' => $client->industry,
            'annualrevenue' => $client->annualrevenue,
            'rating' => $client->rating,
            'ownership' => $client->ownership,
            'siccode' => $client->siccode,
            'tickersymbol' => $client->tickersymbol,
            'phone' => $client->phone,
            'otherphone' => $client->otherphone,
            'email1' => $client->email1,
            'email2' => $client->email2,
            'website' => $client->website,
            'fax' => $client->fax,
            'employees' => $client->employees,
            'emailoptout' => $client->emailoptout,
            'notify_owner' => $client->notify_owner,
            'isconvertedfromlead' => $client->isconvertedfromlead,
            'tags' => $client->tags,
            'is_active' => $client->isActive,
            // address of invoice
            'bill_street' => $client->bill_street,
            'bill_city' => $client->bill_city,
            'bill_state' => $client->bill_state,
            'bill_code' => $client->bill_code,
            'bill_country' => $client->bill_country,
            'bill_pobox' => $client->bill_pobox,
            // address of shipping
            'ship_street' => $client->ship_street,
            'ship_city' => $client->ship_city,
            'ship_state' => $client->ship_state,
            'ship_code' => $client->ship_code,
            'ship_country' => $client->ship_country,
            'ship_pobox' => $client->ship_pobox,
        ];
    }
}
