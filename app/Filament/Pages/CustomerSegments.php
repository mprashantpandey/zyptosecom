<?php

namespace App\Filament\Pages;

use App\Core\Services\AuditService;
use App\Models\CustomerSegment;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class CustomerSegments extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static string $view = 'filament.pages.customer-segments';
    protected static ?string $navigationGroup = 'Users';
    protected static ?string $navigationLabel = 'Customer Segments';
    protected static ?int $navigationSort = 2;

    public ?array $data = [];
    public ?int $previewCount = null;
    public ?CustomerSegment $segmentRecord = null; // For edit mode

    public function mount(?int $record = null): void
    {
        abort_unless(auth()->user()->can('users.segments.manage'), 403);
        
        if ($record) {
            $this->segmentRecord = CustomerSegment::findOrFail($record);
            $this->data = $this->convertRulesToFormData($this->segmentRecord);
            $this->form->fill($this->data);
        } else {
            $this->form->fill();
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Segment Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(CustomerSegment::class, 'name', ignoreRecord: true),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->maxLength(500),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Segment Conditions')
                    ->description('Select conditions to group customers. Leave empty to include all customers.')
                    ->schema([
                        Forms\Components\Select::make('customer_status')
                            ->label('Customer Status')
                            ->options([
                                '' => 'Any Status',
                                'active' => 'Active Only',
                                'blocked' => 'Blocked Only',
                            ])
                            ->helperText('Filter by customer account status')
                            ->default(''),
                        Forms\Components\Select::make('order_count_condition')
                            ->label('Order Count')
                            ->options([
                                '' => 'Any Number of Orders',
                                'none' => 'No Orders',
                                'more_than' => 'More Than X Orders',
                            ])
                            ->live()
                            ->helperText('Filter by number of orders placed')
                            ->default(''),
                        Forms\Components\TextInput::make('order_count_value')
                            ->label('Minimum Orders')
                            ->numeric()
                            ->minValue(1)
                            ->visible(fn ($get) => $get('order_count_condition') === 'more_than')
                            ->required(fn ($get) => $get('order_count_condition') === 'more_than')
                            ->helperText('Customers must have placed at least this many orders'),
                        Forms\Components\Select::make('last_order_condition')
                            ->label('Last Order')
                            ->options([
                                '' => 'Any Time',
                                'within_days' => 'Within Last X Days',
                                'not_within_days' => 'Not Ordered in Last X Days',
                            ])
                            ->live()
                            ->helperText('Filter by when customer last placed an order')
                            ->default(''),
                        Forms\Components\TextInput::make('last_order_days')
                            ->label('Number of Days')
                            ->numeric()
                            ->minValue(1)
                            ->visible(fn ($get) => in_array($get('last_order_condition'), ['within_days', 'not_within_days']))
                            ->required(fn ($get) => in_array($get('last_order_condition'), ['within_days', 'not_within_days']))
                            ->helperText('Number of days to check'),
                        Forms\Components\Select::make('wallet_balance_condition')
                            ->label('Wallet Balance')
                            ->options([
                                '' => 'Any Balance',
                                'greater_than' => 'Greater Than ₹X',
                                'less_than' => 'Less Than ₹X',
                            ])
                            ->live()
                            ->helperText('Filter by wallet balance')
                            ->default(''),
                        Forms\Components\TextInput::make('wallet_balance_value')
                            ->label('Amount (₹)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('₹')
                            ->visible(fn ($get) => in_array($get('wallet_balance_condition'), ['greater_than', 'less_than']))
                            ->required(fn ($get) => in_array($get('wallet_balance_condition'), ['greater_than', 'less_than']))
                            ->helperText('Wallet balance amount'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Preview')
                    ->schema([
                        Forms\Components\Placeholder::make('preview_count')
                            ->label('Matching Customers')
                            ->content(fn () => $this->previewCount !== null 
                                ? number_format($this->previewCount) . ' customers'
                                : 'Click "Preview Count" to see how many customers match these rules'),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('preview')
                                ->label('Preview Count')
                                ->action(function () {
                                    $formData = $this->form->getState();
                                    $rules = $this->convertFormDataToRules($formData);
                                    $segment = new CustomerSegment(['rules' => $rules]);
                                    $this->previewCount = $segment->getMatchingCount();
                                    
                                    Notification::make()
                                        ->title('Preview updated')
                                        ->body("Found {$this->previewCount} matching customers")
                                        ->success()
                                        ->send();
                                }),
                            Forms\Components\Actions\Action::make('save')
                                ->label('Create Segment')
                                ->submit('save')
                                ->color('primary'),
                        ]),
                    ])
                    ->collapsible(),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(CustomerSegment::query())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active')
                    ->sortable(),
                Tables\Columns\TextColumn::make('matching_count')
                    ->label('Matching Customers')
                    ->formatStateUsing(function (CustomerSegment $record) {
                        return number_format($record->getMatchingCount());
                    })
                    ->sortable(false),
                Tables\Columns\TextColumn::make('created_by')
                    ->label('Created By')
                    ->formatStateUsing(fn ($state) => $state ? User::find($state)?->name : 'System')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        true => 'Active',
                        false => 'Inactive',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Matching Customers')
                    ->modalContent(function (CustomerSegment $record) {
                        $users = $record->getMatchingUsers();
                        return view('filament.pages.customer-segments-preview', [
                            'users' => $users,
                            'count' => $users->count(),
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Tables\Actions\EditAction::make()
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(CustomerSegment::class, 'name', ignoreRecord: true),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->maxLength(500),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Forms\Components\TextInput::make('rules.min_orders')
                            ->label('Minimum Orders')
                            ->numeric(),
                        Forms\Components\TextInput::make('rules.max_orders')
                            ->label('Maximum Orders')
                            ->numeric(),
                        Forms\Components\TextInput::make('rules.last_order_days')
                            ->label('Last Order Within (Days)')
                            ->numeric(),
                        Forms\Components\TextInput::make('rules.wallet_balance_gt')
                            ->label('Wallet Balance Greater Than')
                            ->numeric(),
                        Forms\Components\Select::make('rules.status')
                            ->label('Status')
                            ->options([
                                'active' => 'Active Only',
                                'blocked' => 'Blocked Only',
                            ]),
                    ])
                    ->mutateFormDataUsing(function (array $data, CustomerSegment $record): array {
                        $data['rules'] = [
                            'min_orders' => $data['rules']['min_orders'] ?? null,
                            'max_orders' => $data['rules']['max_orders'] ?? null,
                            'last_order_days' => $data['rules']['last_order_days'] ?? null,
                            'wallet_balance_gt' => $data['rules']['wallet_balance_gt'] ?? null,
                            'status' => $data['rules']['status'] ?? null,
                        ];
                        return $data;
                    })
                    ->using(function (array $data, CustomerSegment $record): CustomerSegment {
                        $before = $record->only(['name', 'description', 'rules', 'is_active']);
                        $record->update($data);
                        
                        AuditService::log('segment.updated', $record, $before, $data, ['module' => 'customers']);
                        
                        Notification::make()
                            ->title('Segment updated')
                            ->success()
                            ->send();
                        
                        return $record;
                    }),
                Tables\Actions\DeleteAction::make()
                    ->before(function (CustomerSegment $record) {
                        $before = $record->only(['id', 'name']);
                        AuditService::log('segment.deleted', $record, $before, [], ['module' => 'customers']);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Create Segment')
                    ->modalHeading('Create Customer Segment')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(CustomerSegment::class, 'name'),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->maxLength(500),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Forms\Components\TextInput::make('rules.min_orders')
                            ->label('Minimum Orders')
                            ->numeric(),
                        Forms\Components\TextInput::make('rules.max_orders')
                            ->label('Maximum Orders')
                            ->numeric(),
                        Forms\Components\TextInput::make('rules.last_order_days')
                            ->label('Last Order Within (Days)')
                            ->numeric(),
                        Forms\Components\TextInput::make('rules.wallet_balance_gt')
                            ->label('Wallet Balance Greater Than')
                            ->numeric(),
                        Forms\Components\Select::make('rules.status')
                            ->label('Status')
                            ->options([
                                'active' => 'Active Only',
                                'blocked' => 'Blocked Only',
                            ]),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['rules'] = array_filter([
                            'min_orders' => $data['rules']['min_orders'] ?? null,
                            'max_orders' => $data['rules']['max_orders'] ?? null,
                            'last_order_days' => $data['rules']['last_order_days'] ?? null,
                            'wallet_balance_gt' => $data['rules']['wallet_balance_gt'] ?? null,
                            'status' => $data['rules']['status'] ?? null,
                        ], fn ($value) => $value !== null && $value !== '');
                        $data['created_by'] = auth()->id();
                        return $data;
                    })
                    ->using(function (array $data): CustomerSegment {
                        $segment = CustomerSegment::create($data);
                        
                        AuditService::log('segment.created', $segment, [], [
                            'id' => $segment->id,
                            'name' => $segment->name,
                            'rules' => $segment->rules,
                        ], ['module' => 'customers']);
                        
                        Notification::make()
                            ->title('Segment created')
                            ->success()
                            ->send();
                        
                        return $segment;
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('users.segments.manage'), 403);

        $formData = $this->form->getState();
        $rules = $this->convertFormDataToRules($formData);
        
        $data = [
            'name' => $formData['name'],
            'description' => $formData['description'] ?? null,
            'is_active' => $formData['is_active'] ?? true,
            'rules' => $rules,
        ];
        
        $before = $this->segmentRecord ? $this->segmentRecord->only(['name', 'description', 'rules', 'is_active']) : [];

        DB::transaction(function () use ($data) {
            if ($this->segmentRecord) {
                $this->segmentRecord->update($data);
            } else {
                $this->segmentRecord = CustomerSegment::create(array_merge($data, ['created_by' => auth()->id()]));
            }
        });

        $after = $this->segmentRecord->fresh()->only(['name', 'description', 'rules', 'is_active']);

        AuditService::log(
            $this->segmentRecord->wasRecentlyCreated ? 'segment.created' : 'segment.updated',
            $this->segmentRecord,
            $before,
            $after,
            ['module' => 'customers']
        );

        Notification::make()
            ->title('Segment saved successfully')
            ->success()
            ->send();

        $this->redirect(static::getUrl()); // Redirect to list page
    }

    /**
     * Convert form data to rules JSON
     */
    protected function convertFormDataToRules(array $formData): array
    {
        $rules = [];

        // Customer status
        if (!empty($formData['customer_status'])) {
            $rules['status'] = $formData['customer_status'];
        }

        // Order count
        if ($formData['order_count_condition'] === 'none') {
            $rules['max_orders'] = 0;
        } elseif ($formData['order_count_condition'] === 'more_than' && !empty($formData['order_count_value'])) {
            $rules['min_orders'] = (int)$formData['order_count_value'];
        }

        // Last order
        if ($formData['last_order_condition'] === 'within_days' && !empty($formData['last_order_days'])) {
            $rules['last_order_days'] = (int)$formData['last_order_days'];
        } elseif ($formData['last_order_condition'] === 'not_within_days' && !empty($formData['last_order_days'])) {
            // This would need custom logic in getMatchingUsers, but for now we'll store it
            $rules['last_order_not_within_days'] = (int)$formData['last_order_days'];
        }

        // Wallet balance
        if ($formData['wallet_balance_condition'] === 'greater_than' && !empty($formData['wallet_balance_value'])) {
            $rules['wallet_balance_gt'] = (float)$formData['wallet_balance_value'];
        } elseif ($formData['wallet_balance_condition'] === 'less_than' && !empty($formData['wallet_balance_value'])) {
            $rules['wallet_balance_lt'] = (float)$formData['wallet_balance_value'];
        }

        return $rules;
    }

    /**
     * Convert rules JSON to form data
     */
    protected function convertRulesToFormData(CustomerSegment $segment): array
    {
        $rules = $segment->rules ?? [];
        
        $formData = [
            'name' => $segment->name,
            'description' => $segment->description,
            'is_active' => $segment->is_active,
            'customer_status' => $rules['status'] ?? '',
            'order_count_condition' => '',
            'order_count_value' => null,
            'last_order_condition' => '',
            'last_order_days' => null,
            'wallet_balance_condition' => '',
            'wallet_balance_value' => null,
        ];

        // Order count
        if (isset($rules['max_orders']) && $rules['max_orders'] === 0) {
            $formData['order_count_condition'] = 'none';
        } elseif (isset($rules['min_orders'])) {
            $formData['order_count_condition'] = 'more_than';
            $formData['order_count_value'] = $rules['min_orders'];
        }

        // Last order
        if (isset($rules['last_order_days'])) {
            $formData['last_order_condition'] = 'within_days';
            $formData['last_order_days'] = $rules['last_order_days'];
        } elseif (isset($rules['last_order_not_within_days'])) {
            $formData['last_order_condition'] = 'not_within_days';
            $formData['last_order_days'] = $rules['last_order_not_within_days'];
        }

        // Wallet balance
        if (isset($rules['wallet_balance_gt'])) {
            $formData['wallet_balance_condition'] = 'greater_than';
            $formData['wallet_balance_value'] = $rules['wallet_balance_gt'];
        } elseif (isset($rules['wallet_balance_lt'])) {
            $formData['wallet_balance_condition'] = 'less_than';
            $formData['wallet_balance_value'] = $rules['wallet_balance_lt'];
        }

        return $formData;
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label('Create Segment')
                ->submit('save')
                ->color('primary'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('users.segments.manage') ?? false;
    }
}
