<?php
// app/Application/Repositories/ModuleRepositoryInterface.php

namespace App\Application\Repositories;

interface ModuleRepositoryInterface
{
    /**
     * Get all active modules from vtiger_tab.
     * Returns raw data as stored in BD.
     * 
     * @return array<int, array{tabid: int, name: string, tablabel: string|null, sequence: int}>
     */
    public function getAllActive(): array;
    
    /**
     * Get module data by tabid.
     */
    public function getById(int $tabid): ?array;
    
    /**
     * Get tabid by Vtiger module name (exact match).
     */
    public function getTabIdByName(string $moduleName): ?int;
    
    /**
     * Clear cache.
     */
    public function clearCache(): void;
}