<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Models\Justification;
use App\Support\CertificateResponse;

class CertificateController extends Controller
{
    /** Solo el Director llega acá (middleware `role:director`). */
    public function __invoke(Justification $justification)
    {
        return CertificateResponse::for($justification);
    }
}
