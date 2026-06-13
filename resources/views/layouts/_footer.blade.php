<footer class="footer{{ site_mode() === 'B' ? ' footer--b' : '' }}">
    <div class="container">
        @if(site_mode() === 'B')
            <div class="footer-b-trust" aria-label="服务保障">
                <div class="footer-b-trust__item"><span class="glyphicon glyphicon-lock" aria-hidden="true"></span> 资金托管</div>
                <div class="footer-b-trust__item"><span class="glyphicon glyphicon-ok-circle" aria-hidden="true"></span> 实名认证代购师</div>
                <div class="footer-b-trust__item"><span class="glyphicon glyphicon-plane" aria-hidden="true"></span> 跨境 EMS 直邮</div>
                <div class="footer-b-trust__item"><span class="glyphicon glyphicon-headphones" aria-hidden="true"></span> 7×12 小时客服</div>
            </div>

            <div class="footer-b-links" aria-label="帮助链接">
                <a href="{{ url('/pages/order-flow.html') }}">交易流程</a>
                <a href="{{ url('/pages/change-exchange-return.html') }}">售后规则</a>
                <a href="{{ url('/pages/faq.html') }}">常见问题</a>
                <a href="{{ route('procurement.create') }}">发起求购</a>
            </div>

            <p class="footer-disclaimer text-muted text-center">
                <i class="fa fa-shield" aria-hidden="true"></i>
                岚山集：专业的跨境代购撮合与资金托管平台。所有交易受平台协议约束。
            </p>
            <p class="text-muted text-center footer-b-meta">
                © {{ date('Y') }} 岚山集 · 跨境互助代购大厅
                @if(site_icp_record() !== '')
                    · <a href="{{ site_icp_link() }}" target="_blank" rel="noopener noreferrer">{{ site_icp_record() }}</a>
                @endif
            </p>
        @else
            <div aria-label="帮助导航" style="max-width: 620px; margin: 0 auto 12px; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); column-gap: 12px;">
                <a href="{{ url('/pages/order-flow.html') }}" style="display:flex; justify-content:center; align-items:center; width:100%; min-height:34px; padding:6px 10px; font-size:16px; font-weight:600; color:#2f3b4a; border:1px solid #dde3ea; border-radius:6px; background:#f7f9fb; line-height:1.2; text-decoration:none;">下单流程</a>
                <a href="{{ url('/pages/change-exchange-return.html') }}" style="display:flex; justify-content:center; align-items:center; width:100%; min-height:34px; padding:6px 10px; font-size:16px; font-weight:600; color:#2f3b4a; border:1px solid #dde3ea; border-radius:6px; background:#f7f9fb; line-height:1.2; text-decoration:none;">改/退规则</a>
                <a href="{{ url('/pages/faq.html') }}" style="display:flex; justify-content:center; align-items:center; width:100%; min-height:34px; padding:6px 10px; font-size:16px; font-weight:600; color:#2f3b4a; border:1px solid #dde3ea; border-radius:6px; background:#f7f9fb; line-height:1.2; text-decoration:none;">常见问题</a>
            </div>
            <p class="footer-disclaimer text-muted text-center" style="margin-bottom: 0;">
                &copy; {{ date('Y') }} 岚山选物. All rights reserved.
            </p>
        @endif
    </div>
</footer>
