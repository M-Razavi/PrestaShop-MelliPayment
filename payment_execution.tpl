{capture name=path}{l s='Shipping'}{/capture}
{include file=$tpl_dir./breadcrumb.tpl}
<h2>{l s='Order summary' mod='mellipayment'}</h2>

{assign var='current_step' value='payment'}
{include file=$tpl_dir./order-steps.tpl}


<script type="text/javascript">
setTimeout("document.frmmelli.submit()",10);
</script>

<p align="center" >
{l s='Redirecting to Melli gateway...' mod='mellipayment'}
<br><br>
<img align="middle" src="{$thisPath}loader.gif" />
</p>
<form name="frmmelli" action="{$x_action}" method="post">
<input type="hidden" name="x_login" value="{$x_login}" />
<input type="hidden" name="x_fp_hash" value="{$x_fp_hash}" />
<input type="hidden" name="x_fp_sequence" value="{$x_fp_sequence}" />
<input type="hidden" name="x_fp_timestamp" value="{$x_fp_timestamp}" />
<input type="hidden" name="x_description" value="{$x_description}" />
<input type="hidden" name="x_amount" value="{$x_amount}" />
<input type="hidden" name="x_currency_code" value="{$x_currency_code}" />
</form>
