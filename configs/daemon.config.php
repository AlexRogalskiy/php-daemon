<?php

namespace Configs;

const DEFAULT_FILE_NAME = __FILE__;

const DEFAULT_PROJECT_ID = 1;

// queue options
const DEFAULT_QUEUE_BLOCK_SEND = FALSE;
const DEFAULT_QUEUE_RECEIVE_OPTION = NULL; //MSG_IPC_NOWAIT

const DEFAULT_MESSAGE_SERIALIZATION_NEEDED = FALSE;
const DEFAULT_MESSAGE_TYPE_SEND = 1;
const DEFAULT_MESSAGE_TYPE_RECEIVE = 1;
const DEFAULT_MESSAGE_MAX_SIZE = 4096;

const DEFAULT_DAEMON_LOCK = __DIR__ . '/../daemon.pid';
const DEFAULT_DAEMON_DELAY = 3600;

const DEFAULT_URL = 'https://syn.su/testwork.php';
const DEFAULT_MAIL = 'alexander.rogalsky@yandex.ru';
