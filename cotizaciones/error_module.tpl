<div id="control"> 
  	{include file="$tpl_dir./breadcrumb.tpl"}
	<!-- COLUMNA IZQUIERDA -->
    <div id="left_column" class="column">
    	{$HOOK_LEFT_COLUMN}
		{include file="$tpl_dir./login-usuario.tpl"}
    </div>
    <!-- /COLUMNA IZQUIERDA -->
	
    <!-- CONFIRMACION NORMAL -->
    <div class="col_datos" style="float:left;">
		<p class="error" style="width:550px;">El carrito no puede cargarse o ya hay un pedido en el mismo</p>
    <div class="clearfix"></div>
    </div>
    <!-- /CONFIRMACION NORMAL -->
    
  <div class="clearfix"></div>
</div>
<!-- /columna contenedora -->