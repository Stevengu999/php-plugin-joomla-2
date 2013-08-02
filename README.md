# Plugin for Joomla 2.x and VirtueMart 2.x for pay by PaynetEasy

## Доступная функциональность

Данный  плагин позволяет производить оплату с помощью [merchant PaynetEasy API](http://wiki.payneteasy.com/index.php/PnE:Merchant_API). На текущий момент реализованы следующие платежные методы:
- [ ] [Sale Transactions](http://wiki.payneteasy.com/index.php/PnE:Sale_Transactions)
- [ ] [Preauth/Capture Transactions](http://wiki.payneteasy.com/index.php/PnE:Preauth/Capture_Transactions)
- [ ] [Transfer Transactions](http://wiki.payneteasy.com/index.php/PnE:Transfer_Transactions)
- [ ] [Return Transactions](http://wiki.payneteasy.com/index.php/PnE:Return_Transactions)
- [ ] [Recurrent Transactions](http://wiki.payneteasy.com/index.php/PnE:Recurrent_Transactions)
- [x] [Payment Form Integration](http://wiki.payneteasy.com/index.php/PnE:Payment_Form_integration)
- [ ] [Buy Now Button integration](http://wiki.payneteasy.com/index.php/PnE:Buy_Now_Button_integration)
- [ ] [eCheck integration](http://wiki.payneteasy.com/index.php/PnE:eCheck_integration)
- [ ] [Western Union Integration](http://wiki.payneteasy.com/index.php/PnE:Western_Union_Integration)
- [ ] [Bitcoin Integration](http://wiki.payneteasy.com/index.php/PnE:Bitcoin_integration)
- [ ] [Loan Integration](http://wiki.payneteasy.com/index.php/PnE:Loan_integration)
- [ ] [Qiwi Integration](http://wiki.payneteasy.com/index.php/PnE:Qiwi_integration)
- [ ] [Merchant Callbacks](http://wiki.payneteasy.com/index.php/PnE:Merchant_Callbacks)

## Системные требования

* PHP 5.3 - 5.5
* [Расширение curl](http://php.net/manual/en/book.curl.php)
* [Joomla](http://www.joomla.org/download.html) 2.x (плагин тестировался с версией 2.5)
* [VirtueMart](http://virtuemart.net/downloads) 2.x (плагин тестировался с версией 2.0)

## <a name="get_package"></a> Получение пакета с плагином

### Самостоятельная сборка пакета
1. [Установите composer](http://getcomposer.org/doc/00-intro.md), если его еще нет
2. Клонируйте репозиторий с плагином: `composer create-project payneteasy/php-plugin-joomla-2`
3. Перейдите в папку плагина: `cd php-plugin-joomla-2`
4. Установите зависимости плагина: `composer install --prefer-dist --optimize-autoloader`
5. Упакуйте плагин в архив: `composer archive --format=zip`

## Установка, настройка, удаление плагина

* [Установка плагина](doc/00-installation.md)
* [Настройка плагина](doc/01-configuration.md)
* [Удаление плагина](doc/02-uninstalling.md)