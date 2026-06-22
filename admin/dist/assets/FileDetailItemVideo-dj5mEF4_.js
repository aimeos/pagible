import{B as e,g as t,h as n,nt as r,p as i,y as a}from"./charts-BcPwWJ4d.js";import{r as o}from"./graphql-CcslCevZ.js";import{t as s}from"./VIcon-B4YmdphC.js";import{Kt as c,Sn as l,Tn as u,Vn as d,Yn as f,b as p,gt as m,m as h}from"./index-CSE6bO8v.js";var g=o`
  mutation ($id: ID!, $preview: Upload) {
    saveFile(id: $id, input: {}, preview: $preview) {
      id
      latest {
        data
        created_at
      }
    }
  }
`,_=o`
  mutation ($id: ID!, $preview: Boolean) {
    saveFile(id: $id, input: {}, preview: $preview) {
      id
      latest {
        data
        created_at
      }
    }
  }
`,v={props:{item:{type:Object,required:!0},readonly:{type:Boolean,default:!1}},emits:[`update:item`],data(){return{loading:{}}},setup(){let e=l();return{user:u(),messages:e,url:f,mdiTooltipImage:c,mdiImagePlus:m}},beforeUnmount(){let e=this.$refs.video;e&&(e.pause(),e.removeAttribute(`src`),e.load()),this.loading={}},methods:{addCover(){if(this.readonly)return this.messages.add(this.$gettext(`Permission denied`),`error`);let e=this.$refs.video;if(!e)return this.messages.add(this.$gettext(`No video element found`),`error`);let t=this.item.path.replace(/\.[A-Za-z0-9]+$/,`.png`).split(`/`).pop(),n=document.createElement(`canvas`),r=n.getContext(`2d`);n.width=e.videoWidth,n.height=e.videoHeight,r.drawImage(e,0,0,e.videoWidth,e.videoHeight),n.toBlob(e=>{n.width=0,n.height=0;let r=new File([e],t,{type:`image/png`});this.loading.cover=!0,this.$apollo.mutate({mutation:g,variables:{id:this.item.id,preview:r},context:{hasUpload:!0}}).then(e=>{if(e.errors)throw e.errors;let t=e.data?.saveFile?.latest;t&&(this.item.previews=d(t.data)?.previews||{},this.item.updated_at=t.created_at)}).catch(e=>{this.messages.add(this.$gettext(`Error saving video cover`)+`:
`+e,`error`),this.$log(`FileDetailItemVideo::addCover(): Error saving video cover`,e)}).finally(()=>{this.loading.cover=!1})},`image/png`,1)},removeCover(){if(this.readonly)return this.messages.add(this.$gettext(`Permission denied`),`error`);this.loading.cover=!0,this.item.previews={},this.$apollo.mutate({mutation:_,variables:{id:this.item.id,preview:!1}}).then(e=>{if(e.errors)throw e.errors;let t=e.data?.saveFile?.latest;t&&(this.item.previews=d(t.data)?.previews||{},this.item.updated_at=t.created_at)}).catch(e=>{this.messages.add(this.$gettext(`Error removing video cover`)+`:
`+e,`error`),this.$log(`FileDetailItemVideo::removeCover(): Error removing video cover`,e)}).finally(()=>{this.loading.cover=!1})},uploadCover(e){if(this.readonly)return this.messages.add(this.$gettext(`Permission denied`),`error`);let t=e.target.files[0];if(!t)return this.messages.add(this.$gettext(`No file selected`),`error`);this.loading.cover=!0,this.$apollo.mutate({mutation:g,variables:{id:this.item.id,preview:t},context:{hasUpload:!0}}).then(e=>{if(e.errors)throw e.errors;let t=e.data?.saveFile?.latest;t&&(this.item.previews=d(t.data)?.previews||{},this.item.updated_at=t.created_at)}).catch(e=>{this.messages.add(this.$gettext(`Error uploading video cover`)+`:
`+e,`error`),this.$log(`FileDetailItemVideo::uploadCover(): Error uploading video cover`,e)}).finally(()=>{this.loading.cover=!1})}}},y={class:`editor-container`},b=[`src`],x={key:0,class:`toolbar`},S=[`src`,`alt`],C={key:1};function w(o,c,l,u,d,f){return e(),t(`div`,y,[i(`video`,{ref:`video`,src:u.url(l.item.path),crossorigin:`anonymous`,class:`element`,controls:``},null,8,b),l.readonly?n(``,!0):(e(),t(`div`,x,[Object.values(l.item.previews).length?(e(),t(`img`,{key:0,class:`video-preview`,src:u.url(Object.values(l.item.previews).shift()),alt:l.item.name,onClick:c[0]||=e=>f.removeCover()},null,8,S)):(e(),t(`div`,C,[a(h,{icon:u.mdiTooltipImage,loading:d.loading.cover,title:o.$gettext(`Use as cover image`),class:`btn-cover-use`,onClick:c[1]||=e=>f.addCover()},null,8,[`icon`,`loading`,`title`]),a(h,{icon:``,class:`btn-cover-upload`,loading:d.loading.cover,title:o.$gettext(`Upload cover image`),onClick:c[3]||=e=>o.$refs.coverInput.click()},{default:r(()=>[a(s,{icon:u.mdiImagePlus},null,8,[`icon`]),i(`input`,{ref:`coverInput`,type:`file`,class:`cover-input`,onChange:c[2]||=e=>f.uploadCover(e)},null,544)]),_:1},8,[`loading`,`title`])]))]))])}var T=p(v,[[`render`,w],[`__scopeId`,`data-v-8923a638`]]);export{T as default};