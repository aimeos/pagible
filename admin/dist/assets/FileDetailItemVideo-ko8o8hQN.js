import{Wt as e,X as t,Y as n,q as r,rt as i,wn as a}from"./charts-qBJJ9lq9.js";import{F as o,m as s,r as c}from"./graphql-BnP_iLsi.js";import{s as l}from"./graphql-BRoDkLYC.js";import{S as u}from"./i18n-DRPnG9HE.js";import{Zt as d,ct as f}from"./mdi-BvptiEnw.js";import{t as p}from"./VBtn-CtGXdTrb.js";import{t as m}from"./VIcon-DDPYF8dY.js";import{M as h}from"./index-DtVdozLJ.js";import{o as g}from"./files-Mgyr-dfp.js";var _=l`
  mutation ($id: ID!, $preview: Upload) {
    saveFile(id: $id, input: {}, preview: $preview) {
      id
      latest {
        data
        created_at
      }
    }
  }
`,v=l`
  mutation ($id: ID!, $preview: Boolean) {
    saveFile(id: $id, input: {}, preview: $preview) {
      id
      latest {
        data
        created_at
      }
    }
  }
`,y={props:{item:{type:Object,required:!0},readonly:{type:Boolean,default:!1}},data(){return{loading:{}}},setup(){return{messages:s(),fileurl:o,previewUrl:g,mdiTooltipImage:d,mdiImagePlus:f}},beforeUnmount(){let e=this.$refs.video;e&&(e.pause(),e.removeAttribute(`src`),e.load())},methods:{addCover(){if(this.readonly)return this.messages.denied();let e=this.$refs.video;if(!e)return this.messages.add(this.$gettext(`No video element found`),`error`);let t=this.item.path.replace(/\.[A-Za-z0-9]+$/,`.png`).split(`/`).pop(),n=document.createElement(`canvas`),r=n.getContext(`2d`);n.width=e.videoWidth,n.height=e.videoHeight,r.drawImage(e,0,0,e.videoWidth,e.videoHeight),n.toBlob(e=>{n.width=0,n.height=0,this.cover(new File([e],t,{type:`image/png`}),this.$gettext(`Error saving video cover`))},`image/png`,1)},cover(e,t){return this.loading.cover=!0,this.$apollo.mutate({mutation:e===!1?v:_,variables:{id:this.item.id,preview:e},context:{hasUpload:e!==!1}}).then(e=>{c(`files`);let t=e.data?.saveFile?.latest;t&&(this.item.previews=u(t.data)?.previews||{},this.item.updated_at=t.created_at)}).catch(e=>this.messages.error(t,e)).finally(()=>{this.loading.cover=!1})},removeCover(){if(this.readonly)return this.messages.denied();this.item.previews={},this.cover(!1,this.$gettext(`Error removing video cover`))},uploadCover(e){if(this.readonly)return this.messages.denied();let t=e.target.files[0];if(!t)return this.messages.add(this.$gettext(`No file selected`),`error`);this.cover(t,this.$gettext(`Error uploading video cover`))}}},b={class:`editor-container`},x=[`src`],S={key:0,class:`toolbar`},C=[`src`,`alt`],w={key:1};function T(o,s,c,l,u,d){return e(),t(`div`,b,[r(`video`,{ref:`video`,src:l.fileurl(c.item),crossorigin:`anonymous`,class:`element`,controls:``},null,8,x),c.readonly?n(``,!0):(e(),t(`div`,S,[Object.values(c.item.previews).length?(e(),t(`img`,{key:0,class:`video-preview`,src:l.previewUrl(c.item),alt:c.item.name,onClick:s[0]||=e=>d.removeCover()},null,8,C)):(e(),t(`div`,w,[i(p,{icon:l.mdiTooltipImage,loading:u.loading.cover,title:o.$gettext(`Use as cover image`),class:`btn-cover-use`,onClick:s[1]||=e=>d.addCover()},null,8,[`icon`,`loading`,`title`]),i(p,{icon:``,class:`btn-cover-upload`,loading:u.loading.cover,title:o.$gettext(`Upload cover image`),onClick:s[3]||=e=>o.$refs.coverInput.click()},{default:a(()=>[i(m,{icon:l.mdiImagePlus},null,8,[`icon`]),r(`input`,{ref:`coverInput`,type:`file`,class:`cover-input`,onChange:s[2]||=e=>d.uploadCover(e)},null,544)]),_:1},8,[`loading`,`title`])]))]))])}var E=h(y,[[`render`,T],[`__scopeId`,`data-v-e396f134`]]);export{E as default};