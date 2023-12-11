<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;

/* default/template/payment/mollie_checkout_form.twig */
class __TwigTemplate_25c9c9bf37837ebeff1a74a02b90fe12f3911a2df4241846562151ff7fd74718 extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        echo "<div class=\"checkout-content\">
  <form action=\"";
        // line 2
        echo ($context["action"] ?? null);
        echo "\" method=\"post\" id=\"mollie_payment_form\" ";
        if ( !($context["mollieComponents"] ?? null)) {
            echo " class=\"form-horizontal\" ";
        }
        echo ">
    <div class=\"clearfix\">
      ";
        // line 4
        if ( !twig_test_empty(($context["issuers"] ?? null))) {
            // line 5
            echo "      <div class=\"form-group pull-left\">
        <label class=\"col-sm-6 control-label\"><img src=\"";
            // line 6
            echo ($context["image"] ?? null);
            echo "\" width=\"20\" /> <strong>";
            echo ($context["text_issuer"] ?? null);
            echo ":</strong></label>
        <div class=\"col-sm-6\">
          <select name=\"mollie_issuer\" id=\"mollie_issuers\" class=\"form-control\">
            <option value=\"\">&mdash;</option>
            ";
            // line 10
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(($context["issuers"] ?? null));
            foreach ($context['_seq'] as $context["_key"] => $context["issuer"]) {
                // line 11
                echo "            <option value=\"";
                echo twig_get_attribute($this->env, $this->source, $context["issuer"], "id", [], "any", false, false, false, 11);
                echo "\">";
                echo twig_get_attribute($this->env, $this->source, $context["issuer"], "name", [], "any", false, false, false, 11);
                echo "</option>
            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['issuer'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 13
            echo "          </select>
        </div>
      </div>
      ";
        }
        // line 17
        echo "      ";
        if (($context["mollieComponents"] ?? null)) {
            // line 18
            echo "      <div class=\"left pull-left\">
        <span>";
            // line 19
            echo ($context["text_card_details"] ?? null);
            echo "</span>
      </div><br><br>
      <div id=\"mollie-response\"></div>
      <div class=\"row\">
        <div class=\"form-group col-sm-4\">
          <label class=\"control-label\">";
            // line 24
            echo ($context["entry_card_holder"] ?? null);
            echo "</label>
          <div id=\"card-holder\"></div>
        </div>
        <div class=\"form-group col-sm-4\">
          <label class=\"control-label\">";
            // line 28
            echo ($context["entry_card_number"] ?? null);
            echo "</label>
          <div id=\"card-number\"></div>
        </div>
        <div class=\"form-group col-sm-2\">
          <label class=\"control-label\">";
            // line 32
            echo ($context["entry_expiry_date"] ?? null);
            echo "</label>
          <div id=\"expiry-date\"></div>
        </div>
        <div class=\"form-group col-sm-2\">
          <label class=\"control-label\">";
            // line 36
            echo ($context["entry_verification_code"] ?? null);
            echo "</label>
          <div id=\"verification-code\"></div>
        </div>
      </div>
      <input type=\"hidden\" id=\"card-token\" name=\"cardToken\" value=\"\">
      ";
        }
        // line 42
        echo "      <div class=\"right pull-right buttons\">
        <input type=\"submit\" value=\"";
        // line 43
        echo twig_get_attribute($this->env, $this->source, ($context["message"] ?? null), "get", [0 => "button_confirm"], "method", false, false, false, 43);
        echo "\" id=\"button-confirm\" class=\"button btn btn-primary\" form=\"mollie_payment_form\">
      </div>
      <div class=\"mollie-text\">
        <span><i class=\"fa fa-lock\"></i> ";
        // line 46
        echo ($context["text_mollie_payments"] ?? null);
        echo "</span>
      </div>
    </div>

    <script type=\"text/javascript\">
      (function (\$) {
        \$(function () {
          var issuers = \$(\"#mollie_issuers\"),
              confirm_button_exists = (\$(\"#qc_confirm_order\").length > 0);

          if (issuers.find(\"option\").length === 1) {
            \$.post(\"";
        // line 57
        echo ($context["set_issuer_url"] ?? null);
        echo "\", {mollie_issuer_id: issuers.val()});
          }

          issuers.bind(\"change\", function () {
            \$.post(\"";
        // line 61
        echo ($context["set_issuer_url"] ?? null);
        echo "\", {mollie_issuer_id: \$(this).val()});
          });

          ";
        // line 64
        if (($context["mollieComponents"] ?? null)) {
            // line 65
            echo "            // Initialize the mollie object
            var mollie = Mollie(\"";
            // line 66
            echo ($context["currentProfile"] ?? null);
            echo "\", { locale: \"";
            echo ($context["locale"] ?? null);
            echo "\", testmode: \"";
            echo ($context["testMode"] ?? null);
            echo "\" });

            // Styling
            var options = {
               styles : {
                base: {
                    backgroundColor: '";
            // line 72
            echo (($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 = ($context["base_input_css"] ?? null)) && is_array($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4) || $__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 instanceof ArrayAccess ? ($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4["background_color"] ?? null) : null);
            echo "',
                    color: '";
            // line 73
            echo (($__internal_62824350bc4502ee19dbc2e99fc6bdd3bd90e7d8dd6e72f42c35efd048542144 = ($context["base_input_css"] ?? null)) && is_array($__internal_62824350bc4502ee19dbc2e99fc6bdd3bd90e7d8dd6e72f42c35efd048542144) || $__internal_62824350bc4502ee19dbc2e99fc6bdd3bd90e7d8dd6e72f42c35efd048542144 instanceof ArrayAccess ? ($__internal_62824350bc4502ee19dbc2e99fc6bdd3bd90e7d8dd6e72f42c35efd048542144["color"] ?? null) : null);
            echo "',
                    fontSize: '";
            // line 74
            echo (($__internal_1cfccaec8dd2e8578ccb026fbe7f2e7e29ac2ed5deb976639c5fc99a6ea8583b = ($context["base_input_css"] ?? null)) && is_array($__internal_1cfccaec8dd2e8578ccb026fbe7f2e7e29ac2ed5deb976639c5fc99a6ea8583b) || $__internal_1cfccaec8dd2e8578ccb026fbe7f2e7e29ac2ed5deb976639c5fc99a6ea8583b instanceof ArrayAccess ? ($__internal_1cfccaec8dd2e8578ccb026fbe7f2e7e29ac2ed5deb976639c5fc99a6ea8583b["font_size"] ?? null) : null);
            echo "',
                    '::placeholder' : {
                      color: 'rgba(68, 68, 68, 0.2)',
                    }
                },
                valid: {
                    backgroundColor: '";
            // line 80
            echo (($__internal_68aa442c1d43d3410ea8f958ba9090f3eaa9a76f8de8fc9be4d6c7389ba28002 = ($context["valid_input_css"] ?? null)) && is_array($__internal_68aa442c1d43d3410ea8f958ba9090f3eaa9a76f8de8fc9be4d6c7389ba28002) || $__internal_68aa442c1d43d3410ea8f958ba9090f3eaa9a76f8de8fc9be4d6c7389ba28002 instanceof ArrayAccess ? ($__internal_68aa442c1d43d3410ea8f958ba9090f3eaa9a76f8de8fc9be4d6c7389ba28002["background_color"] ?? null) : null);
            echo "',
                    color: '";
            // line 81
            echo (($__internal_d7fc55f1a54b629533d60b43063289db62e68921ee7a5f8de562bd9d4a2b7ad4 = ($context["valid_input_css"] ?? null)) && is_array($__internal_d7fc55f1a54b629533d60b43063289db62e68921ee7a5f8de562bd9d4a2b7ad4) || $__internal_d7fc55f1a54b629533d60b43063289db62e68921ee7a5f8de562bd9d4a2b7ad4 instanceof ArrayAccess ? ($__internal_d7fc55f1a54b629533d60b43063289db62e68921ee7a5f8de562bd9d4a2b7ad4["color"] ?? null) : null);
            echo "',
                    fontSize: '";
            // line 82
            echo (($__internal_01476f8db28655ee4ee02ea2d17dd5a92599be76304f08cd8bc0e05aced30666 = ($context["valid_input_css"] ?? null)) && is_array($__internal_01476f8db28655ee4ee02ea2d17dd5a92599be76304f08cd8bc0e05aced30666) || $__internal_01476f8db28655ee4ee02ea2d17dd5a92599be76304f08cd8bc0e05aced30666 instanceof ArrayAccess ? ($__internal_01476f8db28655ee4ee02ea2d17dd5a92599be76304f08cd8bc0e05aced30666["font_size"] ?? null) : null);
            echo "',
                },
                invalid: {
                    backgroundColor: '";
            // line 85
            echo (($__internal_01c35b74bd85735098add188b3f8372ba465b232ab8298cb582c60f493d3c22e = ($context["invalid_input_css"] ?? null)) && is_array($__internal_01c35b74bd85735098add188b3f8372ba465b232ab8298cb582c60f493d3c22e) || $__internal_01c35b74bd85735098add188b3f8372ba465b232ab8298cb582c60f493d3c22e instanceof ArrayAccess ? ($__internal_01c35b74bd85735098add188b3f8372ba465b232ab8298cb582c60f493d3c22e["background_color"] ?? null) : null);
            echo "',
                    color: '";
            // line 86
            echo (($__internal_63ad1f9a2bf4db4af64b010785e9665558fdcac0e8db8b5b413ed986c62dbb52 = ($context["invalid_input_css"] ?? null)) && is_array($__internal_63ad1f9a2bf4db4af64b010785e9665558fdcac0e8db8b5b413ed986c62dbb52) || $__internal_63ad1f9a2bf4db4af64b010785e9665558fdcac0e8db8b5b413ed986c62dbb52 instanceof ArrayAccess ? ($__internal_63ad1f9a2bf4db4af64b010785e9665558fdcac0e8db8b5b413ed986c62dbb52["color"] ?? null) : null);
            echo "',
                    fontSize: '";
            // line 87
            echo (($__internal_f10a4cc339617934220127f034125576ed229e948660ebac906a15846d52f136 = ($context["invalid_input_css"] ?? null)) && is_array($__internal_f10a4cc339617934220127f034125576ed229e948660ebac906a15846d52f136) || $__internal_f10a4cc339617934220127f034125576ed229e948660ebac906a15846d52f136 instanceof ArrayAccess ? ($__internal_f10a4cc339617934220127f034125576ed229e948660ebac906a15846d52f136["font_size"] ?? null) : null);
            echo "',
                }
               }
             };

            // Mount credit card fileds
            var cardHolder = mollie.createComponent('cardHolder', options);
            cardHolder.mount('#card-holder');

            var cardNumber = mollie.createComponent('cardNumber', options);
            cardNumber.mount('#card-number');

            var expiryDate = mollie.createComponent('expiryDate', options);
            expiryDate.mount('#expiry-date');

            var verificationCode = mollie.createComponent('verificationCode', options);
            verificationCode.mount('#verification-code');

            document.getElementById(\"mollie_payment_form\").addEventListener('submit', function(e) {
              e.preventDefault();

             mollie.createToken().then(function(result) {
              // Handle the result this can be either result.token or result.error.
                // Add token to the form
                if(result.error !== undefined) {
                  ";
            // line 112
            if (($context["isJournalTheme"] ?? null)) {
                // line 113
                echo "                    triggerLoadingOff();
                  ";
            }
            // line 115
            echo "                  \$('.alert-danger').remove();
                  \$(\"#mollie-response\").after('<div class=\"alert alert-danger\"><i class=\"fa fa-exclamation-circle\"></i> ";
            // line 116
            echo ($context["error_card"] ?? null);
            echo "</div>');
                } else {
                  \$('.alert-danger').remove();
                  \$(\"#card-token\").val(result.token);

                  // Re-submit the form
                  document.getElementById(\"mollie_payment_form\").submit();
                }
                
              });  
            });

          ";
        }
        // line 129
        echo "
          // See if we can find a a confirmation button on the page (i.e. ajax checkouts).
          if (confirm_button_exists) {
            // If we have issuers or mollie components are enabled, show the form.
            var mollieComponents = '";
        // line 133
        echo ($context["mollieComponents"] ?? null);
        echo "';
            if (issuers.length || mollieComponents) {
              \$(\"#mollie_payment_form\").parent().show();
            }

            return;
          }

          // No confirmation button found. Show our own confirmation button.
          \$(\"#button-confirm\").show();
        });
      })(window.jQuery || window.\$);
    </script>
    <style type=\"text/css\">
    ";
        // line 147
        if (($context["mollieComponents"] ?? null)) {
            // line 148
            echo "      .mollie-component {
        ";
            // line 149
            echo (($__internal_887a873a4dc3cf8bd4f99c487b4c7727999c350cc3a772414714e49a195e4386 = ($context["base_input_css"] ?? null)) && is_array($__internal_887a873a4dc3cf8bd4f99c487b4c7727999c350cc3a772414714e49a195e4386) || $__internal_887a873a4dc3cf8bd4f99c487b4c7727999c350cc3a772414714e49a195e4386 instanceof ArrayAccess ? ($__internal_887a873a4dc3cf8bd4f99c487b4c7727999c350cc3a772414714e49a195e4386["other_css"] ?? null) : null);
            echo "
      }
      .mollie-component.is-valid {
        ";
            // line 152
            echo (($__internal_d527c24a729d38501d770b40a0d25e1ce8a7f0bff897cc4f8f449ba71fcff3d9 = ($context["valid_input_css"] ?? null)) && is_array($__internal_d527c24a729d38501d770b40a0d25e1ce8a7f0bff897cc4f8f449ba71fcff3d9) || $__internal_d527c24a729d38501d770b40a0d25e1ce8a7f0bff897cc4f8f449ba71fcff3d9 instanceof ArrayAccess ? ($__internal_d527c24a729d38501d770b40a0d25e1ce8a7f0bff897cc4f8f449ba71fcff3d9["other_css"] ?? null) : null);
            echo "
      }
      .mollie-component.is-invalid {
        ";
            // line 155
            echo (($__internal_f6dde3a1020453fdf35e718e94f93ce8eb8803b28cc77a665308e14bbe8572ae = ($context["invalid_input_css"] ?? null)) && is_array($__internal_f6dde3a1020453fdf35e718e94f93ce8eb8803b28cc77a665308e14bbe8572ae) || $__internal_f6dde3a1020453fdf35e718e94f93ce8eb8803b28cc77a665308e14bbe8572ae instanceof ArrayAccess ? ($__internal_f6dde3a1020453fdf35e718e94f93ce8eb8803b28cc77a665308e14bbe8572ae["other_css"] ?? null) : null);
            echo "
      }      

      .journal-checkout #payment-confirm-button .buttons {
           display: block !important; 
           cursor: unset !important;
      }

      .journal-checkout #payment-confirm-button .buttons .btn {
          pointer-events: unset !important;
      }

      .is-customer .journal-checkout .left {
          display: block; 
      }

      ";
            // line 171
            if (($context["isJournalTheme"] ?? null)) {
                // line 172
                echo "      #button-confirm {
        display: none !important;
      }

      .mollie-text img {
          top: 4px;
      }
      ";
            }
            // line 179
            echo "          
    ";
        }
        // line 181
        echo "    ";
        if ((twig_test_empty(($context["issuers"] ?? null)) &&  !($context["mollieComponents"] ?? null))) {
            // line 182
            echo "      #payment-confirm-button {
        display: none;
      }
    ";
        }
        // line 186
        echo "
    .mollie-text {
      clear: both;
      margin-left: 7px;
    }

    .mollie-text img {
      position: relative;
      top: -3px;
      width: 58px;
      left: -5px;
  }

    </style>
  </form>
</div>
";
    }

    public function getTemplateName()
    {
        return "default/template/payment/mollie_checkout_form.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  358 => 186,  352 => 182,  349 => 181,  345 => 179,  335 => 172,  333 => 171,  314 => 155,  308 => 152,  302 => 149,  299 => 148,  297 => 147,  280 => 133,  274 => 129,  258 => 116,  255 => 115,  251 => 113,  249 => 112,  221 => 87,  217 => 86,  213 => 85,  207 => 82,  203 => 81,  199 => 80,  190 => 74,  186 => 73,  182 => 72,  169 => 66,  166 => 65,  164 => 64,  158 => 61,  151 => 57,  137 => 46,  131 => 43,  128 => 42,  119 => 36,  112 => 32,  105 => 28,  98 => 24,  90 => 19,  87 => 18,  84 => 17,  78 => 13,  67 => 11,  63 => 10,  54 => 6,  51 => 5,  49 => 4,  40 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("", "default/template/payment/mollie_checkout_form.twig", "");
    }
}
