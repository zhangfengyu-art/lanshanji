// 注册一个名为 user-addresses-create-and-edit 的组件
Vue.component('user-addresses-create-and-edit', {
  // 组件的数据
  data() {
    return {
      province: '', // 省
      city: '', // 市
      district: '', // 区
      zip: '', // 区县邮编（china-area-data 区县代码）
    }
  },
  created() {
    const el = document.getElementById('address_init_zip');
    if (el && el.value) {
      this.zip = el.value;
    }
  },
  methods: {
    // 把参数 val 中的值保存到组件的数据中
    onDistrictChanged(val, zipCode) {
      if(val.length === 3) {
        this.province = val[0];
        this.city = val[1];
        this.district = val[2];
      }
      if (zipCode) {
        this.zip = zipCode;
      }
    }
  }
});
