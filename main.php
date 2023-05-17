<?php

ini_set('default_socket_timeout', 10);

abstract class Format {
    const Standard = 0;
    const Success = 1;
    const Warn = 2;
    const Error = 3;
    const Special = 4;
}

abstract class FormatFlags {
    const RED = "\033[31m";
    const GRN = "\033[32m";
    const ORG = "\033[33m";
    const CYN = "\033[36m";
    const BLD = "\033[1m";
    const RES = "\033[0m";
}

readonly class IO {
    public static function p($string, $format=Format::Standard) {
        if (PHP_OS_FAMILY === 'Windows') {
            echo($string);
            return;
        }
        $flag;
        switch ($format) {
            case Format::Success:
                $flag = FormatFlags::GRN;
                break;
            case Format::Warn:
                $flag = FormatFlags::ORG;
                break;
            case Format::Error:
                $flag = FormatFlags::RED;
                break;
            case Format::Special:
                $flag = FormatFlags::CYN;
                break;
            default:
                $flag = "";
                break;
        }
        echo($flag . $string . FormatFlags::RES . "\n");
    }
}

readonly class Api {
    public static function request($params, $url='https://ru.wikipedia.org/w/api.php') {
        $query = $url . '?' . http_build_query($params);
        $data = @file_get_contents($query);
        if ($data === false) { return false; }
        try {
            $json = json_decode($data, true);
        } catch (Exception $e) { return false; }
        return $json;
    }

    public static function get_random_pages($count=1) {
        $params = array(
            'action' => 'query',
            'format' => 'json',
            'formatversion' => '2',
            'generator' => 'random',
            'grnnamespace' => '0',
            'grnlimit' => $count
        );
        $json = Api::request($params);
        if ($json === false) { return false; }
        return array_map(fn($i) => $i['title'], $json['query']['pages']);
    }

    public static function get_links($current) {
        $params = array(
            'action' => 'query',
            'format' => 'json',
            'formatversion' => '2',
            'titles' => $current,
            'prop' => 'links',
            'pllimit' => 'max',
            'redirects'
        );
        $json = Api::request($params);
        if ($json === false) { return false; }
        if (count($json['query']['pages'][0]['links']) === 0) { return 0; }
        return array_map(fn($i) => $i['title'], $json['query']['pages'][0]['links']);
    }
}

readonly class App {
    const COMMANDS = array(
        'help' => 'Получить помощь',
        'exit' => 'Выйти',
        'players' => 'Показать игроков',
        'addplayer' => 'Добавить игрока',
        'rmplayer' => 'Удалить игрока',
        'clearplayers' => 'Удалить всех игроков',
        'reset' => 'Дать всем игрокам новые страницы и сбросить прогресс',
        'start' => 'Начать игру'
    );
    const ERROR_MESSAGES = array(
        'api' => 'Ошибка API. Проверьте интернет и попробуйте снова.',
        'no_cmd' => 'Неизвестная команда. Используйте /help.',
        'no_pla' => 'Игроков нет. Используйте /addplayer.'
    );

    public function start() {
        if (PHP_OS_FAMILY !== 'Darwin' && PHP_OS_FAMILY !== 'Linux') {
            IO::p('ВНИМАНИЕ: у меня есть только машины на MacOS и Linux, поэтому я не могу гарантировать, что скрипт будет работать на устройстве с другой ОС.', Format::Warn);
        }
        $game = new Game();
        while (true) {
            switch (readline('Команда > ')) {
                case '/help':
                    IO::p('Доступные команды:');
                    foreach ($this::COMMANDS as $k => $v) {
                        IO::p("    /$k — $v");
                    }
                    break;
                case '/exit':
                    exit(0);
                    break;
                case '/players':
                    if (count($game->players) == 0) {
                        IO::p($this::ERROR_MESSAGES['no_pla']);
                        break;
                    }
                    IO::p('Игроки:');
                    foreach ($game->players as $i) {
                        IO::p("    $i->name ($i->start -> $i->finish)");
                    }
                    break;
                case '/addplayer':
                    $name = readline('Имя игрока > ');
                    IO::p('Подбор страниц...');
                    $pages = Api::get_random_pages(2);
                    if ($pages === false) {
                        IO::p($this::ERROR_MESSAGES['api'], Format::Error);
                        break;
                    }
                    IO::p("    {$pages[0]} -> {$pages[1]}");
                    $game->players[] = new Player($name, 'Теоретическая физика', 'Энтропия');
                    IO::p('Игрок добавлен.', Format::Success);
                    break;
                case '/rmplayer':
                    $name = readline('Имя игрока > ');
                    for ($i=0; $i < count($game->players); $i++) { 
                        if ($game->players[$i]->name == $name) {
                            unset($game->players[$i]);
                            IO::p('Игрок удален.', Format::Success);
                            break 2;
                        }
                    }
                    IO::p('Игрок не найден.', Format::Warn);
                    break;
                case '/clearplayers':
                    if (count($game->players) == 0) {
                        IO::p($this::ERROR_MESSAGES['no_pla'], Format::Warn);
                        break;
                    }
                    $game->players = [];
                    IO::p('Все игроки удалены.', Format::Success);
                    break;
                case '/reset':
                    if (count($game->players) == 0) {
                        IO::p($this::ERROR_MESSAGES['no_pla'], Format::Warn);
                        break;
                    }
                    IO::p('Сброс страниц...');
                    for ($i=0; $i < count($game->players); $i++) { 
                        $name = $game->players[$i]->name;
                        $pages = Api::get_random_pages(2);
                        if ($pages === false) {
                            IO::p($this::ERROR_MESSAGES['api'], Format::Error);
                            break 2;
                        }
                        $game->players[$i]->start = $pages[0];
                        $game->players[$i]->finish = $pages[1];
                        $game->players[$i]->current = $pages[0];
                        $game->players[$i]->steps = 0;
                        IO::p("    $name ($pages[0] -> $pages[1])");
                    }
                    IO::p('Страницы сброшены.', Format::Success);
                    break;
                case '/start':
                    if (count($game->players) == 0) {
                        IO::p($this::ERROR_MESSAGES['no_pla'], Format::Warn);
                        break;
                    }
                    $game->play();
                    break;
                default:
                    IO::p($this::ERROR_MESSAGES['no_cmd'], Format::Warn);
                    break;
            }
        }
    }
}

class Player {
    public $name;
    public $start;
    public $finish;
    public $current;
    public $steps;

    public function __construct($name, $start, $finish) {
        $this->name = $name;
        $this->start = $start;
        $this->finish = $finish;
        $this->current = $start;
        $this->steps = 0;
    }

    public function move($page) {
        IO::p("$this->name сходил ($this->current => $page).", Format::Special);
        $this->current = $page;
        $this->steps++;
        if ($this->finished()) { IO::p("Достигнута финальная страница.", Format::Success); }
    }

    public function finished() {
        return $this->current == $this->finish;
    }
}

class Game {
    public $players = [];

    public function play() {
        while (true) {
            foreach ($this->players as $i) {
                if (!$i->finished()) {
                    IO::p("Ходит $i->name ($i->current -> $i->finish):", Format::Special);
                    $links = $this->get_links($i->current);
                    if ($links === false) {
                        IO::p(App::ERROR_MESSAGES['api'], Format::Error);
                        return;
                    }
                    if ($links === 0) {
                        IO::p('Ссылок на странице не найдено, переход отменен', Format::Warn);
                        break;
                    }
                    $i->move($this->choose_link($links));
                }
            }
            if ($this->is_over()) {
                IO::p("Игра окончена. Используйте /reset, чтобы начать новую.", Format::Special);
                $this->get_results();
                return;
            }
        }
    }

    public function get_links($page) {
        IO::p("Загрузка ссылок ($page)...");
        return Api::get_links($page);
    }

    public function choose_link($links) {
        $s = 0;
        $e = 10;
        while (true) {
            for ($i=$s; $i < min($e, count($links)); $i++) {
                $ii = $i + 1;
                IO::p("$ii. $links[$i]");
            }
            $input = readline("номер страницы — выбрать / [ENTER] — загрузить еще > ");
            echo("\n");
            if ($input !== '') {
                if (is_numeric($input) && isset($links[(int)$input - 1])) {
                    return $links[(int)$input - 1];
                } else {
                    IO::p('Неверный ввод. Введите номер страницы.', Format::Warn);
                }
            } else {
                $s += 10;
                $e += 10;
            }
        }
    }

    public function is_over() {
        foreach ($this->players as $i) {
            if (!$i->finished()) {
                return false;
            }
        }
        return true;
    }

    public function get_results() {
        IO::p('Результаты:');
        foreach ($this->players as $i) {
            IO::p("   $i->name ($i->start -> $i->finish): $i->steps шаг(а/ов)");
        }
    }
}

$app = new App();
$app->start();
