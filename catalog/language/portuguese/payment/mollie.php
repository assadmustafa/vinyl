<?php
/**
 * Copyright (c) 2012-2015, Mollie B.V.
 * All rights reserved. 
 * 
 * Redistribution and use in source and binary forms, with or without 
 * modification, are permitted provided that the following conditions are met: 
 * 
 * - Redistributions of source code must retain the above copyright notice, 
 *    this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright 
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND ANY 
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED 
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE 
 * DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY 
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES 
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR 
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER 
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT 
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY 
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH 
 * DAMAGE. 
 *
 * @package     Mollie
 * @license     Berkeley Software Distribution License (BSD-License 2) http://www.opensource.org/licenses/bsd-license.php
 * @author      Mollie B.V. <info@mollie.com>
 * @copyright   Mollie B.V.
 * @link        https://www.mollie.com
 */

/**
 * English language file for iDEAL by Mollie
 */

// Text
$_['heading_title'] = 'Pagamento por Mollie';
$_['ideal_title'] = 'Seu pagamento';
$_['text_title'] = 'Pagar online';
$_['text_redirected'] = 'O cliente foi encaminhado para a tela de pagamento';
$_['text_issuer_ideal'] = 'Selecione seu banco';
$_['text_issuer_giftcard'] = 'Selecione seu cartão-presente';
$_['text_issuer_kbc'] = 'Selecione seu botão de pagamento.';
$_['text_issuer_voucher'] = 'Selecione sua marca.';
$_['text_card_details'] = 'Por favor, insira os dados do seu cartão de crédito.';
$_['text_mollie_payments'] = 'Pagamentos seguros fornecidos por %s';
$_['text_recurring_desc'] = 'Pedir %s, %s - %s, Cada %s para %s';
$_['text_recurring'] = '%s a cada %s %s';
$_['text_length'] = ' para pagamentos de %s';
$_['text_trial'] = '%s a cada %s %s para %s pagamentos então ';
$_['text_error_report_success'] = 'O erro foi reportado com sucesso!';

// Button
$_['button_retry'] = 'Retornar à página de checkout';
$_['button_report'] = 'Relatar erro';
$_['button_submit'] = 'Enviar';

// Entry
$_['entry_card_holder'] = 'Nome do titular do cartão';
$_['entry_card_number'] = 'Número do cartão';
$_['entry_expiry_date'] = 'Data de validade';
$_['entry_verification_code'] = 'CVV';

// Error
$_['error_card'] = 'Por favor, verifique os detalhes do seu cartão.';
$_['error_missing_field'] = 'Faltando informações obrigatórias. Verifique se os detalhes básicos do endereço são fornecidos.';

// Status page: payment failed (e.g. cancelled).
$_['heading_failed'] = 'Seu pagamento não foi concluído';
$_['msg_failed'] = 'Infelizmente seu pagamento falhou. Por favor, clique no botão abaixo para retornar à página de checkout e tente configurar um pagamento novamente.';

// Status page: payment pending.
$_['heading_unknown'] = 'Seu pagamento está pendente';
$_['msg_unknown'] = 'Seu pagamento ainda não foi recebido. Enviaremos um e-mail de confirmação assim que o pagamento for recebido.';

// Status page: API failure.
$_['heading_error'] = 'Ocorreu um erro ao configurar o pagamento';
$_['text_error'] = 'Ocorreu um erro ao configurar o pagamento com Mollie. Clique no botão abaixo para retornar à página de checkout.';

// Response
$_['response_success'] = 'O pagamento foi recebido';
$_['response_none'] = 'O pagamento ainda não foi recebido';
$_['response_cancelled'] = 'O cliente cancelou o pagamento';
$_['response_failed'] = 'Infelizmente algo deu errado. Por favor, tente novamente o pagamento.';
$_['response_expired'] = 'O pagamento expirou';
$_['response_unknown'] = 'Ocorreu um erro desconhecido';
$_['shipment_success'] = 'Envio criado';
$_['refund_cancelled'] = 'O reembolso foi cancelado.';
$_['refund_success'] = 'O reembolso foi processado com sucesso!';

// Methods
$_['method_ideal']          = 'iDEAL';
$_['method_creditcard']     = 'Creditcard';
$_['method_bancontact']     = 'Bancontact';
$_['method_banktransfer']   = 'Transferência bancária';
$_['method_belfius']        = 'Belfius Direct Net';
$_['method_kbc']            = 'KBC/CBC Payment Button';
$_['method_sofort']         = 'SOFORT Banking';
$_['method_paypal']         = 'PayPal';
$_['method_paysafecard']    = 'paysafecard';
$_['method_giftcard']       = 'Giftcard';
$_['method_eps']            = 'EPS';
$_['method_giropay']        = 'Giropay';
$_['method_klarnapaylater'] = 'Klarna Pay Later';
$_['method_klarnapaynow']   = 'Klarna Pay Now';
$_['method_klarnasliceit']  = 'Klarna Slice It';
$_['method_przelewy24']  	= 'P24';
$_['method_applepay']    	= 'Apple Pay';
$_['method_voucher']    	= 'Voucher';
$_['method_in3']    	    = 'IN3';

//Round Off Description
$_['roundoff_description'] = 'Diferença de arredondamento devido à conversão de moeda';

//Warning
$_['warning_secure_connection'] = 'Por favor, certifique-se de estar usando uma conexão segura.';
