(function () {
    'use strict';
    const Settings = window.wc.wcSettings.getSetting('officeguy_data', {});
    const Element = window.wp.element;
    const CreateElement = Element.createElement;
    const Title = window.wp.htmlEntities.decodeEntities(Settings.title || 'SUMIT');

    function Content(Props) {
        const Fields = Element.useRef({
            cardNumber: '',
            month: '',
            year: '',
            cvv: '',
            citizenId: '',
            payments: '1'
        });
        const [InputValues, SetInputValues] = Element.useState(Fields.current);
        const Active = Element.useRef(false);
        const Pending = Element.useRef(null);
        const Labels = Settings.labels || {};
        const Mode = Settings.mode || 'redirect';
        let Maximum = Settings.maximumPayments || 1;
        const Total = Props.billing && Props.billing.cartTotal
            ? Number(Props.billing.cartTotal.value) / Math.pow(10, Settings.currencyDecimals) : 0;
        if (Settings.minimumPerPayment > 0) {
            Maximum = Math.min(Maximum, Math.round(Math.round(Total) / Settings.minimumPerPayment));
        }
        if (Settings.minimumForPayments > 0 && Math.round(Total) < Math.round(Settings.minimumForPayments)) {
            Maximum = 1;
        }
        Maximum = Math.max(1, Maximum);
        Element.useEffect(function () {
            Active.current = true;
            return function () {
                Active.current = false;
            };
        }, []);

        const OnPaymentSetup = Props.eventRegistration && Props.eventRegistration.onPaymentSetup;
        const ResponseTypes = Props.emitResponse && Props.emitResponse.responseTypes;
        Element.useEffect(function () {
            if (!OnPaymentSetup || Mode === 'redirect')
                return;

            return OnPaymentSetup(function () {
                function Error(Message) {
                    return {
                        type: ResponseTypes.ERROR,
                        message: Message || Labels.error
                    };
                }
                function Success(Data) {
                    return {
                        type: ResponseTypes.SUCCESS,
                        meta: {
                            paymentMethodData: Data
                        }
                    };
                }
                const Values = Fields.current;
                const Card = Values.cardNumber.replace(/[\s-]/g, '');
                const Now = new Date();
                if (!/^\d{12,19}$/.test(Card))
                    return Error(Labels.invalidCard);
                if (!/^\d{1,2}$/.test(Values.month) || !/^\d{4}$/.test(Values.year) || Number(Values.month) < 1 || Number(Values.month) > 12 || Number(Values.year) < Now.getFullYear() || (Number(Values.year) === Now.getFullYear() && Number(Values.month) < Now.getMonth() + 1))
                    return Error(Labels.invalidExpiry);
                if (Settings.citizenId === 'required' && !Values.citizenId.trim())
                    return Error(Labels.requiredId);
                if ((Settings.cvv === 'required' && !Values.cvv) || (Values.cvv && !/^\d{3,4}$/.test(Values.cvv)))
                    return Error(Labels.invalidCvv);
                const Data = {
                    'og-citizenid': Values.citizenId.trim(),
                    'og-paymentscount': String(Math.max(1, Math.min(Maximum, Number(Values.payments) || 1)))
                };
                if (Mode === 'yes') {
                    Data['og-ccnum'] = Card;
                    Data['og-expmonth'] = Values.month;
                    Data['og-expyear'] = Values.year;
                    Data['og-cvv'] = Values.cvv;
                    return Success(Data);
                }
                if (!window.OfficeGuy || !window.OfficeGuy.Payments || !Settings.companyId || !Settings.publicKey)
                    return Error();
                if (Pending.current)
                    return Pending.current;

                const Request = new Promise(function (Resolve) {
                    let Settled = false;
                    function Finish(Result) {
                        if (Settled)
                            return;

                        Settled = true;
                        window.clearTimeout(Timeout);
                        Resolve(Active.current ? Result : Error());
                    }
                    const Timeout = window.setTimeout(function () {
                        Finish(Error());
                    }, 30000);
                    try {
                        window.OfficeGuy.Payments.Tokenize({
                            CompanyID: Settings.companyId, APIPublicKey: Settings.publicKey,
                            Environment: Settings.environment, ResponseLanguage: Settings.language
                        }, Card, Values.month, Values.year, Values.cvv, Values.citizenId.trim(), function (Response) {
                            if (Response && (Response.Status === 0 || Response.Status === '0' || Response.Status === 'Success') && Response.Data && typeof Response.Data.SingleUseToken === 'string' && Response.Data.SingleUseToken) {
                                Data['og-token'] = Response.Data.SingleUseToken;
                                Finish(Success(Data));
                            } else {
                                Finish(Error(Response && Response.UserErrorMessage));
                            }
                        });
                    } catch (Exception) {
                        Finish(Error());
                    }
                });
                Pending.current = Request;
                Request.then(function () {
                    Pending.current = null;
                });
                return Request;
            });
        }, [OnPaymentSetup, ResponseTypes, Mode, Maximum]);

        function Field(Key, Label, Options, Required, Autocomplete, MaxLength) {
            const Id = 'officeguy-blocks-' + Key;
            const DisplayLabel = Label + (Required ? ' *' : '');
            function Change(Value) {
                Fields.current = { ...Fields.current, [Key]: Value };
                SetInputValues(Fields.current);
            }
            const Attributes = {
                id: Id,
                value: InputValues[Key],
                required: Required,
                autoComplete: Autocomplete || 'off',
                disabled: !!(Props.paymentStatus && Props.paymentStatus.isProcessing)
            };
            if (Options) {
                return CreateElement('div', { key: Key, className: 'wc-block-components-select-input' },
                    CreateElement('div', { className: 'wc-blocks-components-select' },
                        CreateElement('div', { className: 'wc-blocks-components-select__container' },
                            CreateElement('label', { htmlFor: Id, className: 'wc-blocks-components-select__label' }, DisplayLabel),
                            CreateElement('select', {
                                ...Attributes,
                                className: 'wc-blocks-components-select__select',
                                onChange: function (Event) { Change(Event.target.value); }
                            }, Options.map(function (Value) {
                                return CreateElement('option', { key: Value, value: String(Value) }, Value || Label);
                            })),
                            CreateElement('svg', {
                                className: 'wc-blocks-components-select__expand',
                                viewBox: '0 0 24 24', width: 24, height: 24,
                                'aria-hidden': true, focusable: false
                            }, CreateElement('path', { d: 'M6.5 8.5 12 14l5.5-5.5L19 10l-7 7-7-7z' }))
                        )
                    )
                );
            }
            return CreateElement(window.wc.blocksComponents.TextInput, {
                ...Attributes,
                key: Key,
                label: DisplayLabel,
                type: 'text',
                inputMode: Key === 'citizenId' ? 'text' : 'numeric',
                maxLength: MaxLength,
                onChange: Change
            });
        }
        const Children = [CreateElement(Element.RawHTML, { key: 'description' }, Settings.description || '')];
        if (Settings.testingNotice) {
            Children.push(CreateElement('p', {
                key: 'testing',
                role: 'status'
            }, Settings.testingNotice));
        }
        if (Mode === 'redirect') {
            Children.push(CreateElement('p', {
                key: 'redirect'
            }, Settings.redirectMessage));
        } else {
            const Months = [''], Years = [''], Installments = [];
            for (let Month = 1; Month <= 12; Month++)
                Months.push(Month);
            for (let Year = new Date().getFullYear(); Year <= new Date().getFullYear() + 15; Year++)
                Years.push(Year);
            for (let Count = 1; Count <= Maximum; Count++)
                Installments.push(Count);

            Children.push(Field('cardNumber', Labels.cardNumber, null, true, 'cc-number', 25));
            Children.push(Field('month', Labels.month, Months, true, 'cc-exp-month'));
            Children.push(Field('year', Labels.year, Years, true, 'cc-exp-year'));
            if (Settings.citizenId !== 'no')
                Children.push(Field('citizenId', Labels.citizenId, null, Settings.citizenId === 'required', 'off', 30));
            if (Settings.cvv !== 'no')
                Children.push(Field('cvv', Labels.cvv, null, Settings.cvv === 'required', 'cc-csc', 4));
            if (Maximum > 1)
                Children.push(Field('payments', Labels.payments, Installments, false));
        }
        return CreateElement('div', {
            className: 'officeguy-blocks-fields'
        }, Children);
    }
    window.wc.wcBlocksRegistry.registerPaymentMethod({
        name: 'officeguy', paymentMethodId: 'officeguy', gatewayId: 'officeguy',
        label: CreateElement('span', null, Title), ariaLabel: Title,
        content: CreateElement(Content), edit: CreateElement(Content),
        canMakePayment: function () {
            return Settings.available === true;
        },
        supports: {
            features: Settings.supports || ['products'],
            showSavedCards: false,
            showSaveOption: false
        }
    });
}());
