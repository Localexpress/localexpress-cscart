<fieldset>

<div class="control-group">
    <label class="control-label" for="API_key">{__("API_key")}</label>
    <div class="controls">
    <input id="user_key" type="text" name="shipping_data[service_params][API_key]" size="30" value="{$shipping.service_params.API_key}"/>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="test_mode">{__("test_mode")}</label>
    <div class="controls">
    <input type="hidden" name="shipping_data[service_params][test_mode]" value="N" />
    <input id="test_mode" type="checkbox" name="shipping_data[service_params][test_mode]" value="Y" {if $shipping.service_params.test_mode == "Y"}checked="checked"{/if} />
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="max_weight">{__("max_box_weight")}</label>
    <div class="controls">
    <input id="max_weight" type="text" name="shipping_data[service_params][max_weight_of_box]" size="30" value="{$shipping.service_params.max_weight_of_box|default:23}" />
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="height">{__("height")}</label>
    <div class="controls">
    <input id="ship_fedex_height" type="text" name="shipping_data[service_params][height]" size="30" value="{$shipping.service_params.height|default:20}" />
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="width">{__("width")}</label>
    <div class="controls">
    <input id="ship_fedex_width" type="text" name="shipping_data[service_params][width]" size="30" value="{$shipping.service_params.width|default:20}"/>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="length">{__("length")}</label>
    <div class="controls">
    <input id="ship_fedex_length" type="text" name="shipping_data[service_params][length]" size="30" value="{$shipping.service_params.length|default:20}"/>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="price">{__("price")}</label>
    <div class="controls">
    <input id="ship_fedex_length" type="text" name="shipping_data[service_params][price]" size="30" value="{$shipping.service_params.price}"/>
    </div>
</div>

</fieldset>