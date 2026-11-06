// ANDARMA_FA_ICONS_V1 · Generado automáticamente. No editar las exportaciones manualmente.
// Permite conservar los componentes Vue y renderizar Font Awesome 6 instalado.
import { defineComponent, h } from 'vue'

const ICONS = {
  AlertCircle: 'circle-exclamation',
  ArrowLeft: 'arrow-left',
  ArrowRight: 'arrow-right',
  ArrowUpRight: 'arrow-up-right-from-square',
  BadgeCheck: 'circle-check',
  BriefcaseBusiness: 'briefcase',
  Building2: 'building',
  CalendarDays: 'calendar-days',
  ChartNoAxesColumn: 'chart-simple',
  Check: 'check',
  CheckCircle2: 'circle-check',
  ChevronDown: 'chevron-down',
  ChevronLeft: 'chevron-left',
  ChevronRight: 'chevron-right',
  ClipboardList: 'clipboard-list',
  Clock3: 'clock',
  Database: 'database',
  ExternalLink: 'arrow-up-right-from-square',
  Eye: 'eye',
  EyeOff: 'eye-slash',
  FilePenLine: 'file-pen-line',
  FileText: 'file-lines',
  Grid2X2: 'grid2-x2',
  House: 'house',
  Image: 'image',
  Images: 'images',
  Info: 'circle-info',
  KeyRound: 'key-round',
  LayoutDashboard: 'table-columns',
  Link2: 'link',
  LockKeyhole: 'lock-keyhole',
  LogOut: 'right-from-bracket',
  Mail: 'envelope',
  MapPin: 'location-dot',
  MapPinned: 'map-location-dot',
  Menu: 'bars',
  MessageCircle: 'comment',
  MessageSquareQuote: 'comment-dots',
  MousePointerClick: 'arrow-pointer',
  PanelLeftClose: 'panel-left-close',
  PanelsTopLeft: 'panels-top-left',
  Pencil: 'pen',
  Phone: 'phone',
  Plus: 'plus',
  RefreshCw: 'rotate',
  Save: 'floppy-disk',
  Search: 'magnifying-glass',
  Settings2: 'settings2',
  Share2: 'share2',
  ShieldAlert: 'shield-halved',
  ShieldCheck: 'shield-halved',
  SlidersHorizontal: 'sliders',
  Trash2: 'trash-can',
  UploadCloud: 'upload-cloud',
  UserCheck: 'user-check',
  UserCircle2: 'user-circle2',
  UserRound: 'user',
  UserRoundPlus: 'user-plus',
  UserX: 'user-x',
  Users: 'users',
  UsersRound: 'users',
  Wallet: 'wallet',
  WalletCards: 'wallet',
  X: 'xmark',
  ZoomIn: 'magnifying-glass-plus',
  ZoomOut: 'magnifying-glass-minus'
}

function faIcon(name) {
  return defineComponent({
    name: 'AndarmaFA' + name,
    inheritAttrs: false,
    props: { size: [Number, String], color: String, strokeWidth: [Number, String], absoluteStrokeWidth: Boolean },
    setup(props, { attrs }) {
      return () => {
        const px = props.size != null && String(props.size).trim() !== '' ? Number(props.size) : null
        const style = [{
          display: 'inline-flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0,
          ...(Number.isFinite(px) && px > 0 ? { width: px + 'px', height: px + 'px', fontSize: (px * .81) + 'px' } : {}),
          ...(props.color ? { color: props.color } : {})
        }, attrs.style]
        return h('i', {
          ...attrs,
          class: ['fa-solid', 'fa-' + ICONS[name], attrs.class],
          style,
          'aria-hidden': attrs['aria-hidden'] ?? 'true'
        })
      }
    }
  })
}

export const AlertCircle = faIcon('AlertCircle')
export const ArrowLeft = faIcon('ArrowLeft')
export const ArrowRight = faIcon('ArrowRight')
export const ArrowUpRight = faIcon('ArrowUpRight')
export const BadgeCheck = faIcon('BadgeCheck')
export const BriefcaseBusiness = faIcon('BriefcaseBusiness')
export const Building2 = faIcon('Building2')
export const CalendarDays = faIcon('CalendarDays')
export const ChartNoAxesColumn = faIcon('ChartNoAxesColumn')
export const Check = faIcon('Check')
export const CheckCircle2 = faIcon('CheckCircle2')
export const ChevronDown = faIcon('ChevronDown')
export const ChevronLeft = faIcon('ChevronLeft')
export const ChevronRight = faIcon('ChevronRight')
export const ClipboardList = faIcon('ClipboardList')
export const Clock3 = faIcon('Clock3')
export const Database = faIcon('Database')
export const ExternalLink = faIcon('ExternalLink')
export const Eye = faIcon('Eye')
export const EyeOff = faIcon('EyeOff')
export const FilePenLine = faIcon('FilePenLine')
export const FileText = faIcon('FileText')
export const Grid2X2 = faIcon('Grid2X2')
export const House = faIcon('House')
export const Image = faIcon('Image')
export const Images = faIcon('Images')
export const Info = faIcon('Info')
export const KeyRound = faIcon('KeyRound')
export const LayoutDashboard = faIcon('LayoutDashboard')
export const Link2 = faIcon('Link2')
export const LockKeyhole = faIcon('LockKeyhole')
export const LogOut = faIcon('LogOut')
export const Mail = faIcon('Mail')
export const MapPin = faIcon('MapPin')
export const MapPinned = faIcon('MapPinned')
export const Menu = faIcon('Menu')
export const MessageCircle = faIcon('MessageCircle')
export const MessageSquareQuote = faIcon('MessageSquareQuote')
export const MousePointerClick = faIcon('MousePointerClick')
export const PanelLeftClose = faIcon('PanelLeftClose')
export const PanelsTopLeft = faIcon('PanelsTopLeft')
export const Pencil = faIcon('Pencil')
export const Phone = faIcon('Phone')
export const Plus = faIcon('Plus')
export const RefreshCw = faIcon('RefreshCw')
export const Save = faIcon('Save')
export const Search = faIcon('Search')
export const Settings2 = faIcon('Settings2')
export const Share2 = faIcon('Share2')
export const ShieldAlert = faIcon('ShieldAlert')
export const ShieldCheck = faIcon('ShieldCheck')
export const SlidersHorizontal = faIcon('SlidersHorizontal')
export const Trash2 = faIcon('Trash2')
export const UploadCloud = faIcon('UploadCloud')
export const UserCheck = faIcon('UserCheck')
export const UserCircle2 = faIcon('UserCircle2')
export const UserRound = faIcon('UserRound')
export const UserRoundPlus = faIcon('UserRoundPlus')
export const UserX = faIcon('UserX')
export const Users = faIcon('Users')
export const UsersRound = faIcon('UsersRound')
export const Wallet = faIcon('Wallet')
export const WalletCards = faIcon('WalletCards')
export const X = faIcon('X')
export const ZoomIn = faIcon('ZoomIn')
export const ZoomOut = faIcon('ZoomOut')
