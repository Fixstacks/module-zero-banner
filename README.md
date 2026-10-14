# Zero Banner — как пользоваться

Баннер — это карусель из слайдов. Сначала баннер создаётся, потом
ставится на нужные страницы виджетом.

## Install

Requires Magento Open Source 2.4.7–2.4.9 and PHP 8.2–8.4.

```bash
composer require vilmate/module-zero-banner
bin/magento setup:upgrade
bin/magento setup:di:compile           # production mode
bin/magento setup:static-content:deploy # production mode
bin/magento cache:flush
```

## 1. Создать баннер

**Content → Banners → Add New Banner**

- **Name** — название (видно в списке и в выборе виджета).
- **Identifier** — код латиницей, например `summer-sale`.
- **Store View** — в каких магазинах показывать.

**Display** — как выглядит баннер:

| Настройка | Что делает |
|---|---|
| Aspect Ratio | Формат кадра на компьютере: 5:2 — широкий герой, 3:1 — узкая полоса, 16:9, 1:1 и т.д. |
| Aspect Ratio on Phones | Формат на телефоне (до 768px). Обычно лучше выше, например 4:5. «Same as desktop» — как на компьютере. |
| Width | Content width — по ширине контента; Full width — от края до края. |
| Shown at the Top of the Page | Включайте, если баннер — первое, что видно на странице (герой): первая картинка грузится сразу. Для баннеров ниже — выключено, картинки грузятся по мере прокрутки. |
| Show Arrows / Dots / Slide Numbers | Стрелки (на компьютере), точки под баннером, нумерация «01 / 03» перед надписью. |
| Autoplay, Seconds per Slide | Автопрокрутка. По умолчанию выключена; у посетителя есть кнопка паузы. |

## 2. Добавить слайды

В блоке **Slides** → **Add Slide**. Порядок меняется перетаскиванием за
значок ⋮⋮ слева, удаление — корзина справа.

- **Image** — JPG, PNG или GIF, лучше от 2000px по ширине. Можно загрузить
  или выбрать из медиатеки (Select from Gallery). WebP сайт сделает сам.
- **Mobile Image** — необязательно: отдельная картинка для телефона.
- **Image Fit**
  - *Fill the frame* — картинка заполняет весь кадр, лишнее обрезается
    под формат баннера (обычный вариант для фото).
  - *Show the whole image* — картинка видна целиком, вокруг может быть пустое
    поле (для картинок с текстом, логотипов, коллажей).
- **Focal Point** — какую часть фото сохранить при обрезке: Top — если на
  фото человек и обрезается голова, Left/Right — если главное сбоку.
- **Text Position** — где текст: слева, по центру или справа; с этой стороны
  картинка затемняется, чтобы текст читался.
- **Image Alt Text** — короткое описание картинки (для незрячих и поиска).
- **Eyebrow** — маленькая строка над заголовком; **Heading** — заголовок;
  **Text** — пара строк текста.
- **Button Text** + **Link URL** — кнопка и куда ведёт (`/women.html` или
  полный адрес). Без текста кнопки кликабелен весь слайд; без ссылки —
  слайд не кликабелен.
- **Show From / Show To** — даты показа (включительно). Пусто — всегда.
- **Enabled** — временно скрыть слайд, не удаляя.

**Save Banner**. Все размеры картинок готовятся при сохранении.

## 3. Поставить баннер на страницу

**Content → Widgets → Add Widget**

1. Type: **Zero Banner**, Design Theme: **Zero**, → Continue.
2. **Storefront Properties**: Widget Title (для себя), Assign to Store Views.
3. **Layout Updates → Add Layout Update**: где показывать (главная — Specified
   Page → CMS Home Page; категории — Anchor Categories; товары — All Product
   Types и т.д.) и **Container** — место на странице (например, Main Content
   Top — над содержимым).
4. **Widget Options** → Banner: выбрать баннер. **Save**.

Или вставить в CMS-страницу/блок:
`{{widget type="Zero\Banner\Block\Widget\Banner" identifier="summer-sale"}}`

Баннер «Homepage hero» на главной уже стоит виджетом «Homepage hero».

## 4. Когда изменения видны на сайте

Страницы сайта кэшируются. Новые или изменённые слайды появляются, когда
кэш страницы обновится (до суток); если нужно сразу — попросите
разработчика сбросить кэш (Varnish). Слайды с датами показа обновляются
сами — раз в час.

## Общие настройки

**Stores → Configuration → Zero → Banners**: качество картинок (80 —
хороший баланс) и WebP (включено). Применяются ко всем баннерам.
