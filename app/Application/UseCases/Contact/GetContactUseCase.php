<?php

namespace App\Application\UseCases\Contact;

use App\Application\Repositories\ContactRepositoryInterface;
use App\Domain\Entities\Contact;

class GetContactUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $contactRepository
    ) {}

    /**
     * Obtener contacto por ID
     * 
     * @param int $id ID del contacto
     * @return Contact|null Contacto encontrado o null si no existe
     */
    public function execute(int $id): ?Contact
    {
        return $this->contactRepository->findById($id);
    }
}