{{--
    Override of `admin::components.attributes.edit.email`.

    Krayin asks `admin.settings.attributes.check_unique_validation` whether an address is
    taken. That route carries no ACL entry of its own and inherits the nearest mapped
    feature, `admin.settings.*`, so an operator who may create leads but has no Settings
    permission is answered with 401. The component below turned every failed request into
    "Error validating email. Please try again.", which is the error that appeared under a
    perfectly valid address.

    The package asks its own endpoint instead (`admin.leads.check_unique_contact`, named
    under the lead feature, see `ContactUniquenessController`) and treats an unreachable
    check as "not verified": an auxiliary lookup must not block a correct form. The
    server-side `LeadForm` rules stay the authority, and an address typed twice in the same
    form is still caught locally.
--}}
@if (isset($attribute))
    <v-email-component
        :attribute="{{ json_encode($attribute) }}"
        :validations="'{{ $validations }}'"
        :value="{{ json_encode(old($attribute->code) ?? $value) }}"
    >
        <div class="mb-2 flex items-center">
            <input
                type="text"
                class="w-full rounded rounded-r-none border border-gray-300 px-2.5 py-2 text-sm font-normal text-gray-800 hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
            >

            <div class="relative">
                <select class="custom-select w-full rounded rounded-l-none border bg-white px-2.5 py-2 text-sm font-normal text-gray-800 hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 ltr:mr-6 ltr:pr-8 rtl:ml-6 rtl:pl-8">
                    <option value="work" selected>@lang('admin::app.common.custom-attributes.work')</option>
                    <option value="home">@lang('admin::app.common.custom-attributes.home')</option>
                </select>
            </div>
        </div>

        <span class="flex cursor-pointer items-center gap-2 text-brandColor">
            <i class="icon-add text-md !text-brandColor"></i>

            @lang("admin::app.common.custom-attributes.add-more")
        </span>
    </v-email-component>
@endif

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-email-component-template"
    >
        <template v-for="(email, index) in emails">
            <div class="mb-2 flex items-center">
                <x-admin::form.control-group.control
                    type="text"
                    ::id="attribute.code"
                    ::name="`${attribute['code']}[${index}][value]`"
                    class="rounded-r-none"
                    ::rules="getValidation"
                    ::label="attribute.name"
                    v-model="email['value']"
                    ::disabled="isDisabled"
                />

                <div class="relative">
                    <x-admin::form.control-group.control
                        type="select"
                        ::id="attribute.code"
                        ::name="`${attribute['code']}[${index}][label]`"
                        class="rounded-l-none ltr:mr-6 ltr:pr-8 rtl:ml-6 rtl:pl-8"
                        rules="required"
                        ::label="attribute.name"
                        v-model="email['label']"
                        ::disabled="isDisabled"
                    >
                        <option value="work">@lang('admin::app.common.custom-attributes.work')</option>
                        <option value="home">@lang('admin::app.common.custom-attributes.home')</option>
                    </x-admin::form.control-group.control>
                </div>

                <i
                    v-if="emails.length > 1"
                    class="icon-delete ml-1 cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                    @click="remove(email)"
                ></i>
            </div>

            <x-admin::form.control-group.error ::name="`${attribute['code']}[${index}][value]`"/>

            <x-admin::form.control-group.error ::name="`${attribute['code']}[${index}].value`"/>
        </template>

        <span
            class="flex w-fit cursor-pointer items-center gap-2 text-brandColor"
            @click="add"
            v-if="! isDisabled"
        >
            <i class="icon-add text-md !text-brandColor"></i>

            @lang("admin::app.common.custom-attributes.add-more")
        </span>
    </script>

    <script type="module">
        app.component('v-email-component', {
            template: '#v-email-component-template',

            props: ['validations', 'isDisabled', 'attribute', 'value'],

            data() {
                return {
                    emails: this.value || [{'value': '', 'label': 'work'}],
                };
            },

            watch: {
                value(newValue, oldValue) {
                    if (
                        JSON.stringify(newValue)
                        !== JSON.stringify(oldValue)
                    ) {
                        this.emails = newValue || [{'value': '', 'label': 'work'}];
                    }
                },
            },

            computed: {
                getValidation() {
                    return {
                        email: true,
                        unique_email: this.emails ?? [],
                        ...(this.validations === 'required' ? { required: true } : {}),
                    };
                },
            },

            created() {
                this.extendValidations();
            },

            methods: {
                add() {
                    this.emails.push({
                        'value': '',
                        'label': 'work'
                    });
                },

                remove(email) {
                    this.emails = this.emails.filter(item => item !== email);
                },

                extendValidations() {
                    defineRule('unique_email', async (value, emails) => {
                        if (! value || ! value.length) {
                            return true;
                        }

                        const foundEmails = emails.filter(email => email.value === value).length;

                        if (foundEmails > 1) {
                            return 'This email is already in use.';
                        }

                        /**
                         * Only person emails are compared with the contact book, exactly as the
                         * core component does.
                         */
                        if (this.attribute.code !== 'person[emails]') {
                            return true;
                        }

                        try {
                            /**
                             * `attribute.id` is the person id here and not the attribute id: the
                             * lead form builds this component with `{'id': person?.id, ...}`, so an
                             * existing person excludes itself from the comparison while a new one
                             * sends nothing and is compared with the whole contact book.
                             */
                            const { data } = await this.$axios.get('{{ route('admin.leads.check_unique_contact') }}', {
                                params: {
                                    entity_id: this.attribute.id,
                                    entity_type: 'persons',
                                    attribute_code: 'emails',
                                    attribute_value: value
                                }
                            });

                            return data.validated === false ? 'This email is already in use.' : true;
                        } catch (error) {
                            /**
                             * A check that cannot answer must not reject the form: a refused
                             * lookup is exactly what used to print "Error validating email"
                             * under a valid address. The server-side `LeadForm` rules stay the
                             * authority on what is saved.
                             */
                            console.warn('Contact uniqueness check unavailable, skipping it: ', error);

                            return true;
                        }
                    });
                },
            },
        });
    </script>
@endPushOnce
