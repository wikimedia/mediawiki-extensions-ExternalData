<?php
/**
 * Internationalization file for the External Data extension
 *
 * @addtogroup Extensions
*/

$messages = array();

/** English
 * @author Yaron Koren
 */
$messages['en'] = array(
	// user messages
	'getdata' => 'Get data',
	'externaldata-desc' => 'Allows for retrieving data in CSV, JSON and XML formats from both external URLs and local wiki pages',
	'externaldata-ldap-unable-to-connect' => 'Unable to connect to $1\n',
	'externaldata-json-decode-not-supported' => 'Error: json_decode() is not supported in this version of PHP',
	'externaldata-xml-error' => 'XML error: $1 at line $2',
	'externaldata-db-incomplete-information' => '<p>Error: Incomplete information for this server ID.</p>\n',
	'externaldata-db-could-not-get-url' => 'Could not get URL after $1 {{PLURAL:$1|try|tries}}.\n\n',
	'externaldata-db-unknown-type' => '<p>Error: Unknown database type.</p>\n',
	'externaldata-db-could-not-connect' => '<p>Error: Could not connect to database.</p>\n',
	'externaldata-db-no-return-values' => '<p>Error: No return values specified.</p>\n',
	'externaldata-db-invalid-query' => 'Invalid query.',
);

/** Message documentation (Message documentation)
 * @author Dead3y3
 * @author Fryed-peach
 */
$messages['qqq'] = array(
	'externaldata-desc' => '{{desc}}',
);

/** Arabic (العربية)
 * @author Meno25
 */
$messages['ar'] = array(
	'getdata' => 'الحصول على البيانات',
	'externaldata-desc' => 'يسمح باسترجاع البيانات بصيغة CSV، JSON و XML من مسارات خارجية وصفحات الويكي المحلية',
);

/** Egyptian Spoken Arabic (مصرى)
 * @author Meno25
 */
$messages['arz'] = array(
	'getdata' => 'الحصول على البيانات',
	'externaldata-desc' => 'يسمح باسترجاع البيانات بصيغة CSV، JSON و XML من مسارات خارجية وصفحات الويكى المحلية',
);

/** Belarusian (Taraškievica orthography) (Беларуская (тарашкевіца))
 * @author EugeneZelenko
 * @author Jim-by
 */
$messages['be-tarask'] = array(
	'getdata' => 'Атрымаць зьвесткі',
	'externaldata-desc' => 'Дазваляе атрымліваць зьвесткі ў фарматах CSV, JSON і XML з вонкавых крыніц і лякальных старонак вікі',
	'externaldata-ldap-unable-to-connect' => 'Немагчыма далучыцца да $1\\n',
	'externaldata-json-decode-not-supported' => 'Памылка: json_decode() не падтрымліваецца ў гэтай вэрсіі PHP',
	'externaldata-xml-error' => 'Памылка XML: $1 у радку $2',
	'externaldata-db-incomplete-information' => '<p>Памылка: Няпоўная інфармацыя для гэтага ідэнтыфікатара сэрвэра. </p>\\n',
	'externaldata-db-could-not-get-url' => 'Немагчыма атрымаць URL-адрас пасьля $1 {{PLURAL:$1|спробы|спробаў|спробаў}}.\\n\\n',
	'externaldata-db-unknown-type' => '<p>Памылка: Невядомы тып базы зьвестак.</p>\\n',
	'externaldata-db-could-not-connect' => '<p>Памылка: Немагчыма далучыцца да базы зьвестак.</p>\\n',
	'externaldata-db-no-return-values' => '<p>Памылка: Не пазначаныя выніковыя значэньні.</p>\\n',
	'externaldata-db-invalid-query' => 'Няслушны запыт.',
);

/** Bosnian (Bosanski)
 * @author CERminator
 */
$messages['bs'] = array(
	'getdata' => 'Uzmi podatke',
	'externaldata-desc' => 'Omogućuje za preuzimanje podataka u formatima CSV, JSON i XML za vanjske URLove i lokalnu wiki',
);

/** Catalan (Català)
 * @author Solde
 */
$messages['ca'] = array(
	'getdata' => 'Obtenir dades',
);

/** German (Deutsch)
 * @author Purodha
 * @author Umherirrender
 */
$messages['de'] = array(
	'getdata' => 'Daten holen',
	'externaldata-desc' => 'Erlaubt das Einfügen von Daten der Formate CSV, JSON und XML sowohl von externer URL als auch von lokalen Wikiseite',
);

/** Lower Sorbian (Dolnoserbski)
 * @author Michawiki
 */
$messages['dsb'] = array(
	'getdata' => 'Daty wobstaraś',
	'externaldata-desc' => 'Zmóžnja wótwołanje datow w formatach CSV, JSON a XML ako z eksternych URL tak teke lokalnych wikijowych bokow',
	'externaldata-ldap-unable-to-connect' => 'Njemóžno z $1 zwězaś\\n',
	'externaldata-json-decode-not-supported' => 'Zmólka: json_decode() njepódpěra se w toś tej wersiji PHP',
	'externaldata-xml-error' => 'Zmólka XML: $1 na smužce $2',
	'externaldata-db-incomplete-information' => "'''Zmólka: Njedopołne informacije za toś ten serwerowy ID.'''\\n",
	'externaldata-db-could-not-get-url' => 'Njemóžno URL pó $1 {{PLURAL:$1|wopyśe|wopytoma|wopytach|wopytach}} dostaś.\\n\\n',
	'externaldata-db-unknown-type' => "'''Zmólka: Njeznata datowa banka.'''\\n",
	'externaldata-db-could-not-connect' => "'''Zmólka: Njemóžno z datoweju banku zwězaś.'''\\n",
	'externaldata-db-no-return-values' => "'''Zmólka: Žedne gódnoty slědkdaśa pódane.'''\\n",
	'externaldata-db-invalid-query' => 'Njepłaśiwe napšašowanje.',
);

/** Greek (Ελληνικά)
 * @author Dead3y3
 */
$messages['el'] = array(
	'getdata' => 'Πάρε δεδομένα',
	'externaldata-desc' => 'Επιτρέπει την ανάκτηση δεδομένων σε μορφές CSV, JSON και XML και για εξωτερικά URLs και για σελίδες του τοπικού wiki',
);

/** Spanish (Español)
 * @author Crazymadlover
 * @author Sanbec
 */
$messages['es'] = array(
	'getdata' => 'Obtener datos',
	'externaldata-desc' => 'Permite la recuperación de datos en formatos CSV, JSON y XML a partir de URL externos y de páginas wiki locales',
);

/** Basque (Euskara)
 * @author Kobazulo
 */
$messages['eu'] = array(
	'getdata' => 'Datuak eskuratu',
);

/** French (Français)
 * @author Crochet.david
 * @author IAlex
 */
$messages['fr'] = array(
	'getdata' => 'Obtenir des données',
	'externaldata-desc' => 'Permet de récupérer des données en CSV, JSON et XML depuis des URL externes et des pages du wiki',
	'externaldata-ldap-unable-to-connect' => 'Impossible de se connecter à $1\\n',
	'externaldata-json-decode-not-supported' => "Erreur : json_decode() n'est pas supportée dans cette version de PHP",
	'externaldata-xml-error' => 'Erreur XML : $1 à la ligne $2',
	'externaldata-db-incomplete-information' => '<p>Erreur : Informations incomplètes pour cet identifiant de serveur.</p>\\n',
	'externaldata-db-could-not-get-url' => "Impossible d'obtenir l'URL après $1 essais.\\n\\n",
	'externaldata-db-unknown-type' => '<p>ERREUR: Type de base de données inconnu.</p>\\n',
	'externaldata-db-could-not-connect' => '<p>Erreur : Impossible de se connecteur à la base de données.</p>\\n',
	'externaldata-db-no-return-values' => "<p>Erreur : Aucune valeur de retour n'a été spécifiée.</p>\\n",
	'externaldata-db-invalid-query' => 'Requête invalide.',
);

/** Galician (Galego)
 * @author Toliño
 */
$messages['gl'] = array(
	'getdata' => 'Obter os datos',
	'externaldata-desc' => 'Permite a recuperación de datos en formatos CSV, JSON e XML a partir dos enderezos URL externos e mais das páxinas wiki locais',
);

/** Swiss German (Alemannisch)
 * @author Als-Holder
 */
$messages['gsw'] = array(
	'getdata' => 'Date hole',
	'externaldata-desc' => 'Erlaubt Daten abzruefe im CSV, JSON un XML Format vu extärne URL un lokale Wikisyte',
);

/** Gujarati (ગુજરાતી)
 * @author Ashok modhvadia
 */
$messages['gu'] = array(
	'getdata' => 'માહિતી પ્રાપ્ત કરો',
	'externaldata-desc' => 'બાહ્ય કડીઓ અને સ્થાનિક વિકિ પાનાઓ પરથી CSV, JSON અને XML શૈલીમાં માહિતીની પુન:પ્રાપ્તિની છુટ',
);

/** Hebrew (עברית)
 * @author Rotemliss
 * @author YaronSh
 */
$messages['he'] = array(
	'getdata' => 'קבלת נתונים',
	'externaldata-desc' => 'אפשרות לקבלת נתונים בפורמטים: CSV, JSON ו־XML, גם מכתובות חיצוניות וגם מדפי ויקי מקומיים',
);

/** Upper Sorbian (Hornjoserbsce)
 * @author Michawiki
 */
$messages['hsb'] = array(
	'getdata' => 'Daty wobstarać',
	'externaldata-desc' => 'Zmóžnja wotwołanje datow we formatach CSV, JSON a XML z eksternych URL kaž tež lokalnych wikijowych stronow',
	'externaldata-ldap-unable-to-connect' => 'Njemóžno z $1 zwjazać\\n',
	'externaldata-json-decode-not-supported' => 'Zmylk: json_decode() so w tutej wersiji PHP njepodpěruje',
	'externaldata-xml-error' => 'Zmylk XML: $1 na lince $2',
	'externaldata-db-incomplete-information' => "'''Zmylk: Njedospołne informacije za ID tutoho serwera.'''\\n",
	'externaldata-db-could-not-get-url' => 'Njebě móžno URL po $1 {{PLURAL:$1|pospyće|pospytomaj|pospytach|pospytach}} dóstać.\\n\\n',
	'externaldata-db-unknown-type' => "'''Zmylk: Njeznaty typ datoweje banki.'''\\n",
	'externaldata-db-could-not-connect' => "'''Zmylk: Njemóžno z datowej banku zwjazać.'''\\n",
	'externaldata-db-no-return-values' => "'''Zmylk: Žane hódnoty wróćenja podate.'''\\n",
	'externaldata-db-invalid-query' => 'Njepłaćiwe naprašowanje.',
);

/** Interlingua (Interlingua)
 * @author McDutchie
 */
$messages['ia'] = array(
	'getdata' => 'Obtener datos',
	'externaldata-desc' => 'Permitte recuperar datos in le formatos CSV, JSON e XML, e ab adresses URL externe e ab paginas wiki local',
);

/** Indonesian (Bahasa Indonesia)
 * @author Bennylin
 */
$messages['id'] = array(
	'getdata' => 'Ambil data',
	'externaldata-desc' => 'Mengijinkan data untuk diunduh dalam format CSV, JSON, dan XML dari pranala luar maupun dari halaman wiki',
);

/** Italian (Italiano)
 * @author Pietrodn
 */
$messages['it'] = array(
	'getdata' => 'Ottieni dati',
	'externaldata-desc' => 'Consente di recuperare dati nei formati CSV, XML e JSON sia da URL esterni sia da pagine wiki locali',
);

/** Japanese (日本語)
 * @author Fryed-peach
 */
$messages['ja'] = array(
	'getdata' => 'データ取得',
	'externaldata-desc' => '外部URLやローカルのウィキページから、CSV・JSON・XML形式のデータを取得できるようにする',
	'externaldata-ldap-unable-to-connect' => '$1 に接続できません\\n',
	'externaldata-json-decode-not-supported' => 'エラー: json_decode() はこのバージョンの PHP ではサポートされていません',
	'externaldata-xml-error' => 'XMLエラー: 行$2で$1',
	'externaldata-db-incomplete-information' => '<p>エラー: このサーバーIDに対する情報が不十分です。</p>\\n',
	'externaldata-db-could-not-get-url' => '$1回の試行を行いましたが URL を取得できませんでした。\\n\\n',
	'externaldata-db-unknown-type' => '<p>エラー: データベースの種類が不明です。</p>\\n',
	'externaldata-db-could-not-connect' => '<p>エラー: データベースに接続できませんでした。</p>\\n',
	'externaldata-db-no-return-values' => '<p>エラー: 戻り値が指定されていません。</p>\\n',
	'externaldata-db-invalid-query' => '不正なクエリー',
);

/** Khmer (ភាសាខ្មែរ)
 * @author វ័ណថារិទ្ធ
 */
$messages['km'] = array(
	'getdata' => 'យក​ទិន្នន័យ',
);

/** Ripoarisch (Ripoarisch)
 * @author Purodha
 */
$messages['ksh'] = array(
	'getdata' => 'Date holle!',
	'externaldata-desc' => 'Äloup, Date em <i lang="en">CSV</i> Fomaat, em <i lang="en">JSON</i> Fomaat, un em <i lang="en">XML</i> Fomaat fun <i lang="en">URLs</i> un vun Wiki-Sigge ze holle.',
	'externaldata-ldap-unable-to-connect' => 'Kann nit noh $1 verbenge\\n',
	'externaldata-json-decode-not-supported' => '<span style="text-transform: uppercase">Fähler:</span> De Fungxuhn <code lang="en">json_decode()</code> weedt vun heh dä Version vun <i lang="en">PHP</i> nit ongerschtöz.',
	'externaldata-xml-error' => 'Fähler em XML, op Reih $2: $1',
	'externaldata-db-incomplete-information' => '<p><span style="text-transform: uppercase">Fähler:</span> De Enfomazjuhne vör di ßööver Kännong sin nit kumplätt.</p>\\n',
	'externaldata-db-could-not-get-url' => 'Kunnt {{PLURAL:$1|noh eimohl Versöhke|och noh $1 Mohl Versöhke|ohne enne Versöhk}} nix vun däm <i lang="en">URL</i> krijje.\\n\\n',
	'externaldata-db-unknown-type' => '<p><span style="text-transform: uppercase">Fähler:</span> Di Zoot Datebangk es unbikannt.</p>\\n',
	'externaldata-db-could-not-connect' => '<p><span style="text-transform: uppercase">Fähler:</span> Kunnt kein Verbendung noh dä Datebangk krijje.</p>\\n',
	'externaldata-db-no-return-values' => '<p><span style="text-transform: uppercase">Fähler:</span> Kein Wääte för Zerökzeävve aanjejovve.</p>\\n',
	'externaldata-db-invalid-query' => 'Onjöltesch Frooch aan de Datebangk.',
);

/** Luxembourgish (Lëtzebuergesch)
 * @author Robby
 */
$messages['lb'] = array(
	'getdata' => 'Donnéeë kréien',
	'externaldata-desc' => 'Erlaabt et Donnéeën an de Formater CSV, JSON an XML vun externen URLen a lokale Wiki-Säiten ze verschaffen',
);

/** Dutch (Nederlands)
 * @author Siebrand
 */
$messages['nl'] = array(
	'getdata' => 'Gegevens ophalen',
	'externaldata-desc' => "Maakt het mogelijk gegevens in CSV, JSON en XML op te halen van zowel externe URL's als lokale wikipagina's",
	'externaldata-ldap-unable-to-connect' => 'Het was niet mogelijk te verbinden met $1',
	'externaldata-json-decode-not-supported' => 'Fout: json_decode() wordt niet ondersteund in deze versie van PHP',
	'externaldata-xml-error' => 'XML-fout: $1 op regel $2',
	'externaldata-db-incomplete-information' => '<p>Fout: Onvolledige informatie voor dit servernummer.</p>',
	'externaldata-db-could-not-get-url' => 'Na $1 {{PLURAL:$1|poging|pogingen}} gaf de URL geen resultaat.',
	'externaldata-db-unknown-type' => '<p>Fout: onbekend databasetype.</p>',
	'externaldata-db-could-not-connect' => '<p>Fout: het was niet mogelijk met de database te verbinden.</p>',
	'externaldata-db-no-return-values' => '<p>Fout: er zijn geen return-waarden ingesteld.</p>',
	'externaldata-db-invalid-query' => 'Ongeldige zoekopdracht.',
);

/** Norwegian Nynorsk (‪Norsk (nynorsk)‬)
 * @author Gunnernett
 */
$messages['nn'] = array(
	'getdata' => 'Hent data',
	'externaldata-desc' => 'Gjev høve til å lasta inn data i formata CSV, JSON og XML frå både eksterne nettadresser og lokale wikisider',
);

/** Norwegian (bokmål)‬ (‪Norsk (bokmål)‬)
 * @author Nghtwlkr
 */
$messages['no'] = array(
	'getdata' => 'Hent data',
	'externaldata-desc' => 'Gir mulighet til å hente data i formatene CSV, JSON og XML fra både eksterne nettadresser og lokale wikisider',
);

/** Occitan (Occitan)
 * @author Cedric31
 */
$messages['oc'] = array(
	'getdata' => 'Obténer de donadas',
	'externaldata-desc' => "Permet de recuperar de donadas en CSV, JSON e XML dempuèi d'URL extèrnas e de paginas del wiki",
	'externaldata-ldap-unable-to-connect' => 'Impossible de se connectar a $1\\n',
	'externaldata-json-decode-not-supported' => 'Error : json_decode() es pas suportada dins aquesta version de PHP',
	'externaldata-xml-error' => 'Error XML : $1 a la linha $2',
	'externaldata-db-incomplete-information' => '<p>Error : Informacions incompletas per aqueste identificant de servidor.</p>\\n',
	'externaldata-db-could-not-get-url' => "Impossible d'obténer l'URL aprèp $1 {{PLURAL:$1|ensag|ensages}}.\\n\\n",
	'externaldata-db-unknown-type' => '<p>ERROR: Tipe de banca de donadas desconegut.</p>\\n',
	'externaldata-db-could-not-connect' => '<p>Error : Impossible de se connectar a la banca de donadas.</p>\\n',
	'externaldata-db-no-return-values' => '<p>Error : Cap de valor de retorn es pas estada especificada.</p>\\n',
	'externaldata-db-invalid-query' => 'Requèsta invalida.',
);

/** Polish (Polski)
 * @author Leinad
 */
$messages['pl'] = array(
	'getdata' => 'Pobierz dane',
	'externaldata-desc' => 'Umożliwia pobieranie danych w formatach CSV, JSON lub XML zarówno z zewnętrznych adresów URL jak i lokalnych stron wiki',
);

/** Portuguese (Português)
 * @author Waldir
 */
$messages['pt'] = array(
	'getdata' => 'Obter dados',
	'externaldata-desc' => 'Permite a obtenção de dados em CSV, JSON e XML tanto a partir de URLs externos como de páginas wiki locais',
);

/** Brazilian Portuguese (Português do Brasil)
 * @author Eduardo.mps
 */
$messages['pt-br'] = array(
	'getdata' => 'Obter dados',
	'externaldata-desc' => 'Permite a obtenção de dados em CSV, JSON e XML tanto a partir de URLs externos como de páginas wiki locais',
);

/** Romanian (Română)
 * @author KlaudiuMihaila
 */
$messages['ro'] = array(
	'externaldata-desc' => 'Permite obţinerea datelor în format CSV, JSON şi XML din atât adrese URL externe, cât şi pagini wiki locale',
);

/** Tarandíne (Tarandíne)
 * @author Joetaras
 */
$messages['roa-tara'] = array(
	'getdata' => 'Pigghie le date',
	'externaldata-desc' => "Permette de repigghià le data jndr'à le formate CSV, JSON e XML da URL fore a Uicchipèdie e da pàggene locale de Uicchipèdie",
);

/** Russian (Русский)
 * @author Ferrer
 * @author Александр Сигачёв
 */
$messages['ru'] = array(
	'getdata' => 'Получить данные',
	'externaldata-desc' => 'Позволяет получение данных в форматах CSV, JSON и XML, как с внешних адресов, так и с локальных вики-страниц.',
	'externaldata-ldap-unable-to-connect' => 'Не удаётся подключиться к $1\\n',
	'externaldata-json-decode-not-supported' => 'Ошибка. json_decode() не поддерживается в данной версии PHP',
	'externaldata-xml-error' => 'Ошибка XML. $1 в строке $2',
	'externaldata-db-incomplete-information' => '<p>ОШИБКА. Неполная информация для этого ID сервера.</p>\\n',
	'externaldata-db-could-not-get-url' => 'Не удалось получить URL после $1 попыток.\\n\\n',
	'externaldata-db-unknown-type' => '<p>ОШИБКА. Неизвестный тип базы данных.</p>\\n',
	'externaldata-db-could-not-connect' => '<p>ОШИБКА. Не удаётся подключиться к базе данных.</p>\\n',
	'externaldata-db-no-return-values' => '<p>ОШИБКА. Не указаны возвращаемые значение.</p>\\n',
	'externaldata-db-invalid-query' => 'Ошибочный запрос.',
);

/** Slovak (Slovenčina)
 * @author Helix84
 */
$messages['sk'] = array(
	'getdata' => 'Získať dáta',
	'externaldata-desc' => 'Umožňuje získavanie údajov vo formátoch CSV, JSON a XML z externých URL aj z lokálnych wiki stránok',
	'externaldata-ldap-unable-to-connect' => 'Nepodarilo sa pripojiť k $1\\n',
	'externaldata-json-decode-not-supported' => 'Chyba: táto verzia PHP nepodporuje json_decode()',
	'externaldata-xml-error' => 'Chyba XML: $1 na riadku $2',
	'externaldata-db-incomplete-information' => '<p>Chyba: Nekompletné informácie s týmto ID servera.</p>\\n',
	'externaldata-db-could-not-get-url' => 'Nepodarilo sa získať URL po $1 {{PLURAL:$1|pokuse|pokusoch}}.\\n\\n',
	'externaldata-db-unknown-type' => '<p>Chyba: Neznámy typ databázy.</p>\\n',
	'externaldata-db-could-not-connect' => '<p>Chyba: Nepodarilo sa pripojiť k databáze.</p>\\n',
	'externaldata-db-no-return-values' => '<p>Chyba: Neboli zadané žiadne návratové hodnoty.</p>\\n',
	'externaldata-db-invalid-query' => 'Neplatná požiadavka.',
);

/** Serbian Cyrillic ekavian (ћирилица)
 * @author Михајло Анђелковић
 */
$messages['sr-ec'] = array(
	'getdata' => 'Преузми податке',
	'externaldata-desc' => 'Омогућава преузимање података у CSV, JSON и XML форматима, како преко спољашњих веза, тако и са локалних вики-страна',
);

/** Swedish (Svenska)
 * @author Najami
 */
$messages['sv'] = array(
	'getdata' => 'Hämta data',
	'externaldata-desc' => 'Tillåter att hämta data i formaten CSV, JSON och XML från både externa URL:er och lokala wikisidor',
);

/** Tagalog (Tagalog)
 * @author AnakngAraw
 */
$messages['tl'] = array(
	'getdata' => 'Kunin ang dato',
	'externaldata-desc' => 'Nagpapahintulot sa muling pagkuha ng datong nasa mga anyong CSV, JSON at XML na kapwa mula sa panlabas na mga URL at pampook na mga pahina ng wiki',
);

/** Turkish (Türkçe)
 * @author Karduelis
 */
$messages['tr'] = array(
	'getdata' => 'Veri al',
);

/** Veps (Vepsan kel')
 * @author Игорь Бродский
 */
$messages['vep'] = array(
	'getdata' => 'Sada andmused',
);

/** Vietnamese (Tiếng Việt)
 * @author Vinhtantran
 */
$messages['vi'] = array(
	'getdata' => 'Lấy dữ liệu',
	'externaldata-desc' => 'Cho phép truy xuất dữ liệu theo định dạng CSV, JSON và XML từ cả URL bên ngoài lẫn các trang wiki bên trong',
);

