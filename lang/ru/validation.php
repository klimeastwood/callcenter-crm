<?php

/**
 * Russian validation lines.
 *
 * Laravel falls back to `fallback_locale` (en) for every key that is missing
 * here, so the file only has to stay in sync with the rules the CRM actually
 * uses - the set below mirrors `lang/en/validation.php`.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'accepted' => 'Вы должны принять :attribute.',
    'active_url' => 'Поле :attribute содержит недействительный URL.',
    'after' => 'В поле :attribute должна быть дата после :date.',
    'after_or_equal' => 'В поле :attribute должна быть дата после или равная :date.',
    'alpha' => 'Поле :attribute может содержать только буквы.',
    'alpha_dash' => 'Поле :attribute может содержать только буквы, цифры, дефис и подчёркивание.',
    'alpha_num' => 'Поле :attribute может содержать только буквы и цифры.',
    'array' => 'Поле :attribute должно быть массивом.',
    'before' => 'В поле :attribute должна быть дата до :date.',
    'before_or_equal' => 'В поле :attribute должна быть дата до или равная :date.',
    'between' => [
        'numeric' => 'Поле :attribute должно быть между :min и :max.',
        'file' => 'Размер файла в поле :attribute должен быть от :min до :max килобайт.',
        'string' => 'Количество символов в поле :attribute должно быть от :min до :max.',
        'array' => 'Количество элементов в поле :attribute должно быть от :min до :max.',
    ],
    'boolean' => 'Поле :attribute должно иметь значение «да» или «нет».',
    'confirmed' => 'Поле :attribute не совпадает с подтверждением.',
    'date' => 'Поле :attribute не является датой.',
    'date_equals' => 'Поле :attribute должно быть датой, равной :date.',
    'date_format' => 'Поле :attribute не соответствует формату :format.',
    'different' => 'Поля :attribute и :other должны различаться.',
    'digits' => 'Длина цифрового поля :attribute должна быть :digits.',
    'digits_between' => 'Длина цифрового поля :attribute должна быть от :min до :max.',
    'dimensions' => 'Поле :attribute содержит изображение недопустимого размера.',
    'distinct' => 'Поле :attribute содержит повторяющееся значение.',
    'email' => 'Поле :attribute должно быть корректным адресом электронной почты.',
    'ends_with' => 'Поле :attribute должно заканчиваться одним из следующих значений: :values.',
    'exists' => 'Выбранное значение для :attribute некорректно.',
    'file' => 'Поле :attribute должно быть файлом.',
    'filled' => 'Поле :attribute обязательно для заполнения.',
    'gt' => [
        'numeric' => 'Поле :attribute должно быть больше :value.',
        'file' => 'Размер файла в поле :attribute должен быть больше :value килобайт.',
        'string' => 'Количество символов в поле :attribute должно быть больше :value.',
        'array' => 'Количество элементов в поле :attribute должно быть больше :value.',
    ],
    'gte' => [
        'numeric' => 'Поле :attribute должно быть больше или равно :value.',
        'file' => 'Размер файла в поле :attribute должен быть больше или равен :value килобайт.',
        'string' => 'Количество символов в поле :attribute должно быть больше или равно :value.',
        'array' => 'Количество элементов в поле :attribute должно быть больше или равно :value.',
    ],
    'image' => 'Поле :attribute должно быть изображением.',
    'in' => 'Выбранное значение для :attribute некорректно.',
    'in_array' => 'Поле :attribute не существует в :other.',
    'integer' => 'Поле :attribute должно быть целым числом.',
    'ip' => 'Поле :attribute должно быть корректным IP-адресом.',
    'ipv4' => 'Поле :attribute должно быть корректным IPv4-адресом.',
    'ipv6' => 'Поле :attribute должно быть корректным IPv6-адресом.',
    'json' => 'Поле :attribute должно быть корректной JSON-строкой.',
    'lt' => [
        'numeric' => 'Поле :attribute должно быть меньше :value.',
        'file' => 'Размер файла в поле :attribute должен быть меньше :value килобайт.',
        'string' => 'Количество символов в поле :attribute должно быть меньше :value.',
        'array' => 'Количество элементов в поле :attribute должно быть меньше :value.',
    ],
    'lte' => [
        'numeric' => 'Поле :attribute должно быть меньше или равно :value.',
        'file' => 'Размер файла в поле :attribute должен быть меньше или равен :value килобайт.',
        'string' => 'Количество символов в поле :attribute должно быть меньше или равно :value.',
        'array' => 'Количество элементов в поле :attribute должно быть меньше или равно :value.',
    ],
    'max' => [
        'numeric' => 'Поле :attribute не может быть больше :max.',
        'file' => 'Размер файла в поле :attribute не может быть больше :max килобайт.',
        'string' => 'Количество символов в поле :attribute не может превышать :max.',
        'array' => 'Количество элементов в поле :attribute не может превышать :max.',
    ],
    'mimes' => 'Поле :attribute должно быть файлом одного из типов: :values.',
    'mimetypes' => 'Поле :attribute должно быть файлом одного из типов: :values.',
    'min' => [
        'numeric' => 'Поле :attribute должно быть не меньше :min.',
        'file' => 'Размер файла в поле :attribute должен быть не меньше :min килобайт.',
        'string' => 'Количество символов в поле :attribute должно быть не меньше :min.',
        'array' => 'Количество элементов в поле :attribute должно быть не меньше :min.',
    ],
    'multiple_of' => 'Поле :attribute должно быть кратным :value.',
    'not_in' => 'Выбранное значение для :attribute некорректно.',
    'not_regex' => 'Поле :attribute имеет некорректный формат.',
    'numeric' => 'Поле :attribute должно быть числом.',
    'password' => 'Неверный пароль.',
    'present' => 'Поле :attribute должно присутствовать.',
    'regex' => 'Поле :attribute имеет некорректный формат.',
    'required' => 'Поле :attribute обязательно для заполнения.',
    'required_if' => 'Поле :attribute обязательно для заполнения, когда :other равно :value.',
    'required_unless' => 'Поле :attribute обязательно для заполнения, когда :other не равно :values.',
    'required_with' => 'Поле :attribute обязательно для заполнения, когда указано :values.',
    'required_with_all' => 'Поле :attribute обязательно для заполнения, когда указаны :values.',
    'required_without' => 'Поле :attribute обязательно для заполнения, когда не указано :values.',
    'required_without_all' => 'Поле :attribute обязательно для заполнения, когда не указан ни один из :values.',
    'same' => 'Значения полей :attribute и :other должны совпадать.',
    'size' => [
        'numeric' => 'Поле :attribute должно быть равно :size.',
        'file' => 'Размер файла в поле :attribute должен быть равен :size килобайт.',
        'string' => 'Количество символов в поле :attribute должно быть равно :size.',
        'array' => 'Количество элементов в поле :attribute должно быть равно :size.',
    ],
    'starts_with' => 'Поле :attribute должно начинаться с одного из следующих значений: :values.',
    'string' => 'Поле :attribute должно быть строкой.',
    'timezone' => 'Поле :attribute должно быть корректным часовым поясом.',
    'unique' => 'Такое значение поля :attribute уже занято.',
    'uploaded' => 'Не удалось загрузить :attribute.',
    'url' => 'Поле :attribute имеет некорректный формат URL.',
    'uuid' => 'Поле :attribute должно быть корректным UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | Field names the messages can mention, so a Russian line reads «Поле
    | электронная почта обязательно для заполнения» instead of naming the
    | database column.
    |
    */

    'attributes' => [
        'address' => 'адрес',
        'city' => 'город',
        'country' => 'страна',
        'current_password' => 'текущий пароль',
        'description' => 'описание',
        'email' => 'электронная почта',
        'expected_close_date' => 'ожидаемая дата закрытия',
        'image' => 'изображение',
        'lead_value' => 'сумма лида',
        'locale' => 'язык',
        'name' => 'имя',
        'network' => 'сеть',
        'password' => 'пароль',
        'password_confirmation' => 'подтверждение пароля',
        'phone' => 'телефон',
        'postcode' => 'индекс',
        'role_id' => 'роль',
        'state' => 'область',
        'status' => 'статус',
        'timezone' => 'часовой пояс',
        'title' => 'заголовок',
        'view_permission' => 'права просмотра',
        'wallet_address' => 'адрес кошелька',
    ],

];
