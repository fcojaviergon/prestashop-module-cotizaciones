<script type="text/javascript">
	$(document).ready(function() {
		// $('#left_column').css('display', 'none');
		$('.breadcrumb').css('display', 'none');
		$('.grid_5').css('margin', '0px');
		$('.grid_5').css('padding', '0px');
	});
</script>
<div id="control">


    <!-- CONFIRMACION SIMPLE -->
    <div id="confirmacion-simple-mod">
    	<h1>Su Cotizaci&oacute;n en <span class="bold">{$shop_name}</span> fue enviada</h1>
    	<div class="mensaje">
    		<span class="bold">{l s='Muchas gracias por su solicitud de cotización. Pronto nos contactaremos con usted.' mod='quoteorder'}</span><br/> Para volver a {$shop_name}, <a href="{$link->getPageLink('contact-form.php', true)}">{l s='customer support' mod='cotizaciones'}</a>.
      	</div>
    	<div class="clearfix"></div>
    </div>
    <!-- /CONFIRMACION SIMPLE -->
  	<div class="clearfix"></div>
</div>
