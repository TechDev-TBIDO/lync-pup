<?php

namespace App\Http\Requests\Admin;

class UpdateCohortRequest extends StoreCohortRequest
{
    // Same rules as Store — see StoreCohortRequest::rules(). Numbering is
    // fixed at creation, so nothing here needs to know about the current
    // cohort's own row.
}
