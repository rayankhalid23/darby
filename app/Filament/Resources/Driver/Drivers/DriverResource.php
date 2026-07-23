<?php

namespace App\Filament\Resources\Driver\Drivers;

use App\Filament\Resources\Driver\Drivers\Pages\CreateDriver;
use App\Filament\Resources\Driver\Drivers\Pages\EditDriver;
use App\Filament\Resources\Driver\Drivers\Pages\ListDrivers;
use App\Filament\Resources\Driver\Drivers\Schemas\DriverForm;
use App\Filament\Resources\Driver\Drivers\Tables\DriversTable;
use App\Models\Driver\Driver;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return DriverForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DriversTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDrivers::route('/'),
            'create' => CreateDriver::route('/create'),
            'edit' => EditDriver::route('/{record}/edit'),
        ];
    }
}
