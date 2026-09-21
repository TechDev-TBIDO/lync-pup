<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Startup\StoreTeamMemberRequest as FounderStoreTeamMemberRequest;

/**
 * A Core Team row on the admin Information Sheet page.
 *
 * Holds the reviewer to exactly the same column rules and messages the
 * founder's own row is held to (see Startup\StoreTeamMemberRequest and SheetRowRules), so
 * a row that could not be saved from the founder's page cannot be saved from
 * here either - and the inline errors read the same on both pages. Only who
 * may make the request differs.
 */
class StoreTeamMemberRequest extends FounderStoreTeamMemberRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }
}
