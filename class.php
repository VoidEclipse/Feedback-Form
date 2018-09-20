<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * Класс, для новой формы
 *
 *
 * Bitrix vars
 *
 * @var array $arParams
 * @var array $arResult
 * @var CBitrixComponent $this
 * @global CMain $APPLICATION
 * @global CUser $USER
 */


class FormNew extends CBitrixComponent
{
    /**
     *
     * Главный метод обработчика
     *
     */
    protected function action()
    {

        $this->checkParams();

        $this->core();

        $this->checkErrors();
    }

    /**
     *
     *  Проверка параметров
     *
     */
    protected function checkParams()
    {
        global $USER;
        $this->arResult["PARAMS_HASH"] = md5(serialize($this->arParams) . $this->GetTemplateName());
        $this->arParams["USE_CAPTCHA"] = (($this->arParams["USE_CAPTCHA"] != "N" && !$USER->IsAuthorized()) ? "Y" : "N");
        $this->arParams["EVENT_NAME"] = trim($this->arParams["EVENT_NAME"]);
        if ($this->arParams["EVENT_NAME"] == '') {
            $this->arParams["EVENT_NAME"] = "FEEDBACK_FORM";
        }
        $this->arParams["EMAIL_TO"] = trim($this->arParams["EMAIL_TO"]);
        if ($this->arParams["EMAIL_TO"] == '') {
            $this->arParams["EMAIL_TO"] = COption::GetOptionString("main", "email_from");
        }
        $this->arParams["OK_TEXT"] = trim($this->arParams["OK_TEXT"]);
        if ($this->arParams["OK_TEXT"] == '') {
            $this->arParams["OK_TEXT"] = GetMessage("MF_OK_MESSAGE");
        }
    }


    /**
     *
     * Проверка ошибок
     *
     *
     */
    protected function checkErrors()
    {
        global $APPLICATION;
        global $USER;

        if (empty($this->arResult["ERROR_MESSAGE"])) {
            if ($USER->IsAuthorized()) {
                $this->arResult["AUTHOR_NAME"] = $USER->GetFormattedName(false);
                $this->arResult["AUTHOR_EMAIL"] = htmlspecialcharsbx($USER->GetEmail());
            } else {
                if (strlen($_SESSION["MF_NAME"]) > 0) {
                    $this->arResult["AUTHOR_NAME"] = htmlspecialcharsbx($_SESSION["MF_NAME"]);
                }
                if (strlen($_SESSION["MF_EMAIL"]) > 0) {
                    $this->arResult["AUTHOR_EMAIL"] = htmlspecialcharsbx($_SESSION["MF_EMAIL"]);
                }
            }
        }

        if ($this->arParams["USE_CAPTCHA"] == "Y") {
            $this->arResult["capCode"] = htmlspecialcharsbx($APPLICATION->CaptchaGetCode());
        }
    }

    /*
     *
     * Основа обработчика
     *
     */
    protected function core()
    {
        global $APPLICATION;

        if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST["submit"] <> '' && (!isset($_POST["PARAMS_HASH"]) || $this->arResult["PARAMS_HASH"] === $_POST["PARAMS_HASH"])) {
            $this->arResult["ERROR_MESSAGE"] = array();
            if (check_bitrix_sessid()) {
                if (empty($this->arParams["REQUIRED_FIELDS"]) || !in_array("NONE",
                        $this->arParams["REQUIRED_FIELDS"])) {
                    if ((empty($this->arParams["REQUIRED_FIELDS"]) || in_array("NAME",
                                $this->arParams["REQUIRED_FIELDS"])) && strlen($_POST["user_name"]) <= 1) {
                        $this->arResult["ERROR_MESSAGE"][] = GetMessage("MF_REQ_NAME");
                    }
                    if ((empty($this->arParams["REQUIRED_FIELDS"]) || in_array("PHONE",
                                $this->arParams["REQUIRED_FIELDS"])) && strlen($_POST["user_phone"]) <= 1) {
                        $this->arResult["ERROR_MESSAGE"][] = GetMessage("MF_REQ_PHONE");
                    }
                    if ((empty($this->arParams["REQUIRED_FIELDS"]) || in_array("EMAIL",
                                $this->arParams["REQUIRED_FIELDS"])) && strlen($_POST["user_email"]) <= 1) {
                        $this->arResult["ERROR_MESSAGE"][] = GetMessage("MF_REQ_EMAIL");
                    }
                    if ((empty($this->arParams["REQUIRED_FIELDS"]) || in_array("MESSAGE",
                                $this->arParams["REQUIRED_FIELDS"])) && strlen($_POST["MESSAGE"]) <= 3) {
                        $this->arResult["ERROR_MESSAGE"][] = GetMessage("MF_REQ_MESSAGE");
                    }
                }

                if (strlen($_POST["user_email"]) > 1 && !check_email($_POST["user_email"])) {
                    $this->arResult["ERROR_MESSAGE"][] = GetMessage("MF_EMAIL_NOT_VALID");
                }

                // подключаем капчу
                if ($this->arParams["USE_CAPTCHA"] == "Y") {
                    $captcha_code = $_POST["captcha_sid"];
                    $captcha_word = $_POST["captcha_word"];
                    $cpt = new CCaptcha();
                    $captchaPass = COption::GetOptionString("main", "captcha_password", "");
                    if (strlen($captcha_word) > 0 && strlen($captcha_code) > 0) {
                        if (!$cpt->CheckCodeCrypt($captcha_word, $captcha_code, $captchaPass)) {
                            $this->arResult["ERROR_MESSAGE"][] = GetMessage("MF_CAPTCHA_WRONG");
                        }
                    } else {
                        $this->arResult["ERROR_MESSAGE"][] = GetMessage("MF_CAPTHCA_EMPTY");
                    }

                }

                if (empty($this->arResult["ERROR_MESSAGE"])) {
                    // сюда передаем данные с полей
                    $arFields = Array(
                        "AUTHOR" => $_POST["user_name"],
                        "AUTHOR_EMAIL" => $_POST["user_email"],
                        "EMAIL_TO" => $this->arParams["EMAIL_TO"],
                        "TEXT" => $_POST["MESSAGE"],
                        "PHONE" => $_POST["user_phone"],
                        "REST_URL" => $this->arParams["REST_URL"]
                    );
                    if (!empty($this->arParams["EVENT_MESSAGE_ID"])) {
                        foreach ($this->arParams["EVENT_MESSAGE_ID"] as $v) {
                            if (IntVal($v) > 0) {
                                // тут отправляем данные 
                                $this->emailSend($arFields, $v);
                                $this->crmSend();
                            }
                        }
                    } else {
                        CEvent::Send($this->arParams["EVENT_NAME"], SITE_ID, $arFields);
                    }
                    $_SESSION["MF_NAME"] = htmlspecialcharsbx($_POST["user_name"]);
                    $_SESSION["MF_EMAIL"] = htmlspecialcharsbx($_POST["user_email"]);
                    LocalRedirect($APPLICATION->GetCurPageParam("success=" . $this->arResult["PARAMS_HASH"],
                        Array("success")));
                }

                $this->arResult["MESSAGE"] = htmlspecialcharsbx($_POST["MESSAGE"]);
                $this->arResult["AUTHOR_NAME"] = htmlspecialcharsbx($_POST["user_name"]);
                $this->arResult["AUTHOR_PHONE"] = htmlspecialcharsbx($_POST["user_phone"]);
                $this->arResult["AUTHOR_EMAIL"] = htmlspecialcharsbx($_POST["user_email"]);
            } else {
                $this->arResult["ERROR_MESSAGE"][] = GetMessage("MF_SESS_EXP");
            }
        } elseif ($_REQUEST["success"] == $this->arResult["PARAMS_HASH"]) {
            $this->arResult["OK_MESSAGE"] = $this->arParams["OK_TEXT"];
        }

    }


    /**
     *
     * Метод отправляет данные с полей на почту
     *
     */
    protected function emailSend($arFields, $v)
    {
        CEvent::Send($this->arParams["EVENT_NAME"], SITE_ID, $arFields, "N", IntVal($v));
    }


    /**
     *
     * Метод создает лид в CRM
     *
     */
    protected function crmSend()
    {
        $queryUrl = $this->arParams["REST_URL"] . 'crm.lead.add.json'; // URL для вызова метода

        // Массив с параметрами, передающимеся в CRM
        $queryData = http_build_query(array(
            'fields' => array(
                "TITLE" => $_POST["user_name"],
                "NAME" => $_POST["user_name"],
                "STATUS_ID" => "NEW",
                "OPENED" => "Y",
                "ASSIGNED_BY_ID" => 199,
                "PHONE" => array(
                    array(
                        "VALUE" => $_POST['user_phone'],
                        "VALUE_TYPE" => "WORK"
                    )
                ),
                "EMAIL" => array(
                    array(
                        "VALUE" => $_POST["user_email"],
                        "VALUE_TYPE" => "WORK"
                    )
                ),
                "COMMENTS" => $_POST["MESSAGE"],
            ),
            'params' => array("REGISTER_SONET_EVENT" => "Y")
        ));

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POST => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $queryUrl,
            CURLOPT_POSTFIELDS => $queryData,
        ));

        $result = curl_exec($curl);
        curl_close($curl);

        $result = json_decode($result, 1);

        if (array_key_exists('error', $result)) {
            echo "Ошибка при сохранении лида: " . $result['error_description'] . "<br/>";
        }

    }

    /**
     *
     * Точка входа
     *
     */
    public function executeComponent()
    {
        $this->action();
        $this->includeComponentTemplate();
    }

}

