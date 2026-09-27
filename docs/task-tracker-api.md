# Учебный API таск-трекера

## Как устроен запрос

`routes/api.php → middleware → контроллер → сервис → модель → PostgreSQL`.

- Контроллер проверяет поля через `validate()` и возвращает JSON.
- Сервис выполняет действие и ищет записи только среди данных текущего пользователя.
- Модель описывает таблицу, разрешённые поля и связи.
- `auth:sanctum` проверяет токен, `EnsureAccountIsActive` — блокировку аккаунта.

Чужая запись возвращает 404, отсутствие токена — 401, блокировка — 403,
ошибка данных — 422, превышение частоты регистрации/входа — 429.
Успешное создание — 201, удаление — 204 без тела ответа.

## Запуск

Из папки backend: `php artisan serve`.
Список маршрутов: `php artisan route:list --path=api`.
Тесты: `php artisan test` (SQLite в памяти из phpunit.xml, рабочая БД не очищается).

Миграции уже применены к настроенной PostgreSQL. `migrate:fresh` для запуска не нужен.
Новых библиотек не добавлено. Существующая документация Scramble не изменена.

## Первый запрос

В Postman выбери **POST**, адрес `http://127.0.0.1:8000/api/register`,
Body → raw → JSON. Открытие адреса в строке браузера отправляет GET и не регистрирует пользователя.

Заголовки:

```http
Accept: application/json
Content-Type: application/json
```

```json
{
  "name": "Ильяс",
  "email": "ilyas@example.com",
  "password": "LearningApi123!",
  "password_confirmation": "LearningApi123!"
}
```

Email передавай в нижнем регистре. Из ответа возьми `data.token`.
Для защищённых запросов добавь `Authorization: Bearer <токен>`.
Токен действует 7 дней; POST /api/logout отзывает текущий токен.

## Маршруты

| Метод | Путь (после /api) | Действие |
|---|---|---|
| POST | /register | Регистрация |
| POST | /login | Вход: email, password |
| POST | /logout | Выход |
| GET | /me | Текущий пользователь |
| GET, POST | /projects | Список / создание проекта |
| GET, PATCH, DELETE | /projects/{project} | Чтение / изменение / удаление |
| GET, POST | /projects/{project}/tasks | Список / создание задачи |
| GET, PATCH, DELETE | /tasks/{task} | Чтение / изменение / удаление |
| GET, POST | /tasks/{task}/comments | Список / создание комментария |
| DELETE | /comments/{comment} | Удаление комментария |
| GET, POST | /tags | Список / создание тега |
| DELETE | /tags/{tag} | Удаление тега |

Список возвращает `data` и данные пагинации; отдельная запись — объект внутри `data`.

## Пример последовательности

1. POST /api/projects: `{"name":"Изучение Laravel","description":"Учебный проект"}`.
2. POST /api/tags: `{"name":"backend"}`.
3. Возьми реальные ID проекта и тега из ответов.
4. POST /api/projects/{project}/tasks:

```json
{
  "title": "Написать API",
  "status": "todo",
  "due_date": "2026-10-15",
  "tag_ids": [1]
}
```

5. PATCH /api/tasks/{task}: `{"status":"in_progress"}`.
6. POST /api/tasks/{task}/comments: `{"body":"Авторизация готова"}`.
7. GET /api/projects/{project}/tasks?status=in_progress&tag_id=1&page=1.

В PATCH отсутствующее поле не меняется. `tag_ids: []` снимает все теги,
`description: null` или `due_date: null` очищает поле.
Допустимые статусы: todo, in_progress, done.

Названия, описания и комментарии — обычный текст. Клиент должен выводить их как текст,
не вставляя через innerHTML. Владелец определяется по токену, а не по user_id из запроса.

Удаление проекта удаляет задачи, комментарии и связи тегов.
Удаление тега удаляет только связи — задачи остаются.
