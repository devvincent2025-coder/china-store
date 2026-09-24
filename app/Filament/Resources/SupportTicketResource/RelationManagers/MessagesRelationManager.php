<?php

namespace App\Filament\Resources\SupportTicketResource\RelationManagers;

use App\Notifications\SupportTicketReplied;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Conversation';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('body')
                    ->label('Reply')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('From'),
                Tables\Columns\TextColumn::make('body')->wrap(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'asc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Reply')
                    ->mutateFormDataUsing(function (array $data) {
                        $data['user_id'] = auth()->id();

                        return $data;
                    })
                    ->after(function ($record) {
                        // The ticket owner replying to their own resolved/closed ticket reopens it
                        // (see SupportTicketController::reply()) — an admin reply should move a
                        // fresh ticket into 'pending' (awaiting the customer), never silently leave
                        // it 'open' as if nobody had looked at it yet.
                        $ticket = $record->ticket;

                        if ($ticket->status === 'open') {
                            $ticket->update(['status' => 'pending']);
                        }

                        $ticket->user->notify(new SupportTicketReplied($record));
                    }),
            ])
            ->actions([])
            ->paginated(false);
    }
}
