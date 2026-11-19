@props(['minor'])<span {{ $attributes }}>{{ 'Rp '.number_format(((int)$minor)/100,abs((int)$minor)%100===0?0:2,',','.') }}</span>
