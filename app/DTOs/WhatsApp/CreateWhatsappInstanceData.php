<?php

namespace App\DTOs\WhatsApp;

use App\Support\CurrentCompany;
use Illuminate\Http\Request;

class CreateWhatsappInstanceData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly int $companyId,
        public readonly int $sectorId,
        public readonly string $instanceName,
        public readonly ?string $phoneNumber,
        public readonly array $metadata,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $companyId = app(CurrentCompany::class)->id();

        if ($companyId === null) {
            throw new \RuntimeException('Empresa atual nao encontrada.');
        }

        return new self(
            $companyId,
            (int) $request->integer('sector_id'),
            (string) $request->string('instance_name'),
            $request->filled('phone_number') ? (string) $request->string('phone_number') : null,
            (array) $request->input('metadata', []),
        );
    }
}
