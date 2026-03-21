<?php

namespace App\Application\UseCases\Contact;

use App\Application\Repositories\ContactRepositoryInterface;
use InvalidArgumentException;

class DeleteContactUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $contactRepository
    ) {}

    /**
     * Eliminar contacto (soft delete)
     * 
     * Marca el contacto como eliminado en vtiger_crmentity sin borrar los datos.
     * Esto mantiene la integridad referencial con otras tablas relacionadas.
     * 
     * @param int $id ID del contacto a eliminar
     * @return bool True si se eliminó exitosamente
     * 
     * @throws InvalidArgumentException Si el contacto no existe o ya está eliminado
     */
    public function execute(int $id): bool
    {
        // Validar que el contacto existe
        $contact = $this->contactRepository->findById($id);
        
        if (!$contact) {
            throw new InvalidArgumentException('El contacto no existe');
        }

        // Validar que no esté ya eliminado
        if ($contact->deleted === 1) {
            throw new InvalidArgumentException('El contacto ya está eliminado');
        }

        // Verificar si el contacto está relacionado con cotizaciones activas
        // (Opcional: descomentar si quieres validar esto)
        /*
        if ($this->hasActiveQuotes($id)) {
            throw new InvalidArgumentException(
                'No se puede eliminar el contacto porque tiene cotizaciones activas asociadas'
            );
        }
        */

        // Ejecutar soft delete
        return $this->contactRepository->delete($id);
    }

    /**
     * Verificar si el contacto tiene cotizaciones activas relacionadas
     * 
     * @param int $contactId ID del contacto
     * @return bool True si tiene cotizaciones activas
     * 
     * @note Implementar si necesitas validar relaciones antes de eliminar
     */
    private function hasActiveQuotes(int $contactId): bool
    {
        // Esto requeriría un método adicional en el repositorio o un servicio de cotizaciones
        // Ejemplo conceptual:
        // return $this->quoteRepository->hasActiveQuotesByContact($contactId);
        return false;
    }
}