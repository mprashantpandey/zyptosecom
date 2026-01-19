<?php

namespace App\Filament\Resources;

use App\Core\Services\AuditService;
use App\Core\Services\NotificationService;
use App\Core\Services\SampleDataFactory;
use App\Core\Services\TemplateRendererService;
use App\Filament\Resources\EmailTemplateResource\Pages;
use App\Models\NotificationTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class EmailTemplateResource extends Resource
{
    protected static ?string $model = NotificationTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationGroup = 'Email';
    protected static ?string $navigationLabel = 'Email Templates';
    protected static ?int $navigationSort = 1;

    /**
     * Scope to email channel only
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('channel', 'email');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Friendly name for this template'),
                        Forms\Components\TextInput::make('subject')
                            ->required()
                            ->maxLength(500)
                            ->helperText('Email subject line. Use {{variable}} for dynamic content.'),
                        Forms\Components\RichEditor::make('body')
                            ->required()
                            ->columnSpanFull()
                            ->helperText('Email body (HTML allowed). Use {{variable}} for dynamic content.'),
                        Forms\Components\Select::make('locale')
                            ->label('Language')
                            ->options([
                                'en' => 'English',
                                'hi' => 'Hindi',
                            ])
                            ->default('en')
                            ->searchable(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active templates can be used'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Available Variables')
                    ->description('These variables can be used in subject and body: {{variable_name}}')
                    ->schema([
                        Forms\Components\Placeholder::make('variables_info')
                            ->label('Common Variables')
                            ->content('order_id, order_number, customer_name, customer_email, total_amount, items_count, delivery_address, tracking_number, estimated_delivery, payment_method, order_date, otp, expires_in, amount, balance_before, balance_after, transaction_type, reason, transaction_date, refund_id, refund_amount, refund_method, refund_date')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(NotificationTemplate::where('channel', 'email'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('locale')
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
                Tables\Filters\SelectFilter::make('locale')
                    ->options([
                        'en' => 'English',
                        'hi' => 'Hindi',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('sample_type')
                            ->label('Sample Data')
                            ->options([
                                'order' => 'Order Sample',
                                'otp' => 'OTP Sample',
                                'wallet' => 'Wallet Sample',
                                'refund' => 'Refund Sample',
                            ])
                            ->default('order')
                            ->required(),
                    ])
                    ->modalHeading('Email Preview')
                    ->modalContent(function (NotificationTemplate $record, array $data) {
                        $renderer = app(TemplateRendererService::class);
                        $sampleFactory = app(SampleDataFactory::class);
                        $sampleData = $sampleFactory->getSample($data['sample_type'] ?? 'order');
                        $rendered = $renderer->render($record, $sampleData);
                        
                        return view('filament.pages.email-preview', [
                            'subject' => $rendered['subject'],
                            'body' => $rendered['body'],
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Tables\Actions\Action::make('send_test')
                    ->label('Send Test')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('recipient')
                            ->label('Recipient Email')
                            ->email()
                            ->required(),
                        Forms\Components\Select::make('sample_type')
                            ->label('Sample Data')
                            ->options([
                                'order' => 'Order Sample',
                                'otp' => 'OTP Sample',
                                'wallet' => 'Wallet Sample',
                                'refund' => 'Refund Sample',
                            ])
                            ->default('order')
                            ->required(),
                    ])
                    ->action(function (NotificationTemplate $record, array $data) {
                        abort_unless(auth()->user()->can('email.templates.test'), 403);
                        
                        try {
                            $notificationService = app(NotificationService::class);
                            $sampleFactory = app(SampleDataFactory::class);
                            $sampleData = $sampleFactory->getSample($data['sample_type']);
                            
                            $log = $notificationService->send(
                                'test',
                                'email',
                                $data['recipient'],
                                $sampleData
                            );
                            
                            if ($log->status === 'sent') {
                                Notification::make()
                                    ->title('Test email sent successfully')
                                    ->body("Check notification logs for details")
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Test email failed')
                                    ->body($log->error_message ?? 'Unknown error')
                                    ->danger()
                                    ->send();
                            }
                            
                            AuditService::log('email.test_sent', $record, [], [
                                'recipient' => $data['recipient'],
                                'sample_type' => $data['sample_type'],
                            ], ['module' => 'email']);
                            
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Test failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (NotificationTemplate $record) {
                        $before = $record->only(['id', 'name', 'subject']);
                        AuditService::log('email.template_deleted', $record, $before, [], ['module' => 'email']);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['is_active' => true]);
                            }
                            Notification::make()
                                ->title('Templates activated')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['is_active' => false]);
                            }
                            Notification::make()
                                ->title('Templates deactivated')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailTemplates::route('/'),
            'create' => Pages\CreateEmailTemplate::route('/create'),
            'edit' => Pages\EditEmailTemplate::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('email.templates.view') ?? false;
    }
}
