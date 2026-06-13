<footer class="payment-gateway-footer">
  <p>&copy; {{ date('Y') }} 岚山集 · 跨境互助代购大厅
    @if(site_icp_record() !== '')
      · <a href="{{ site_icp_link() }}" target="_blank" rel="noopener noreferrer">{{ site_icp_record() }}</a>
    @endif
  </p>
</footer>
